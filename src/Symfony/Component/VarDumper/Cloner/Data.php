<?php

namespace Patchwork\Dumper\Collector;

use Patchwork\Dumper\Dumper\DumperInterface;
use Patchwork\Dumper\Dumper\Cursor;

class Data
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getRawData()
    {
        return $this->data;
    }

    public function dump(DumperInterface $dumper)
    {
        $refs = array(0);
        $dumper->dumpStart();
        $this->dumpItem($dumper, new Cursor, $refs, $this->data[0][0]);
        $dumper->dumpEnd();
    }

    private function dumpItem($dumper, $cursor, &$refs, $item)
    {
        $cursor->refIndex = $cursor->refTo = $cursor->refIsHard = false;

        if ($item instanceof \stdClass) {
            if (isset($item->val)) {
                if (isset($item->ref)) {
                    if (isset($refs[$r = $item->ref])) {
                        $cursor->refTo = $refs[$r];
                        $cursor->refIsHard = true;
                    } else {
                        $cursor->refIndex = $refs[$r] = ++$refs[0];
                    }
                }
                $item = $item->val;
            }
            if (isset($item->ref)) {
                if (isset($refs[$r = $item->ref])) {
                    if (false === $cursor->refTo) {
                        $cursor->refTo = $refs[$r];
                        $cursor->refIsHard = isset($item->count);
                    }
                } elseif (false !== $cursor->refIndex) {
                    $refs[$r] = $cursor->refIndex;
                } else {
                    $cursor->refIndex = $refs[$r] = ++$refs[0];
                }
            }
            $cut = isset($item->cut) ? $item->cut : 0;

            if (isset($item->pos) && false === $cursor->refTo) {
                $children = $this->data[$item->pos];

                if ($cursor->stop) {
                    if ($cut >= 0) {
                        $cut += count($children);
                    }
                    $children = array();
                }
            } else {
                $children = array();
            }
            switch (true) {
                case isset($item->bin):
                    $dumper->dumpString($cursor, $item->bin, true, $cut);

                    return;

                case isset($item->str):
                    $dumper->dumpString($cursor, $item->str, false, $cut);

                    return;

                case isset($item->count):
                    $dumper->enterArray($cursor, $item->count, !empty($item->indexed), (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, empty($item->indexed) ? $cursor::HASH_ASSOC : $cursor::HASH_INDEXED);
                    $dumper->leaveArray($cursor, $item->count, !empty($item->indexed), (bool) $children, $cut);

                    return;

                case isset($item->class):
                    $dumper->enterObject($cursor, $item->class, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $cursor::HASH_OBJECT);
                    $dumper->leaveObject($cursor, $item->class, (bool) $children, $cut);

                    return;

                case isset($item->res):
                    $dumper->enterResource($cursor, $item->res, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $cursor::HASH_RESOURCE);
                    $dumper->leaveResource($cursor, $item->res, (bool) $children, $cut);

                    return;
            }
        }

        if ('array' === $type = gettype($item)) {
            $dumper->enterArray($cursor, 0, true, 0, 0);
            $dumper->leaveArray($cursor, 0, true, 0, 0);
        } else {
            $dumper->dumpScalar($cursor, $type, $item);
        }
    }

    private function dumpChildren($dumper, $parentCursor, &$refs, $children, $hashCut, $hashType)
    {
        if ($children) {
            $cursor = clone $parentCursor;
            ++$cursor->depth;
            $cursor->hashType = $hashType;
            $cursor->hashIndex = 0;
            $cursor->hashLength = count($children);
            $cursor->hashCut = $hashCut;
            foreach ($children as $cursor->hashKey => $child) {
                $this->dumpItem($dumper, $cursor, $refs, $child);
                ++$cursor->hashIndex;
                if ($cursor->stop) {
                    $parentCursor->stop = true;

                    return $hashCut >= 0 ? $hashCut + $children - $cursor->hashIndex : $hashCut;
                }
            }
        }

        return $hashCut;
    }

    /**
     * @internal
     */
    public static function utf8Encode($s)
    {
        if (function_exists('iconv')) {
            return iconv('CP1252', 'UTF-8', $s);
        } else {
            $s .= $s;
            $len = strlen($s);

            for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
                switch (true) {
                    case $s[$i] < "\x80": $s[$j] = $s[$i]; break;
                    case $s[$i] < "\xC0": $s[$j] = "\xC2"; $s[++$j] = $s[$i]; break;
                    default: $s[$j] = "\xC3"; $s[++$j] = chr(ord($s[$i]) - 64); break;
                }
            }

            return substr($s, 0, $j);
        }
    }
}
