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
            if (isset($item->pos) && false === $cursor->refTo) {
                $children = count($this->data[$item->pos]);
            } else {
                $children = 0;
            }
            $cut = isset($item->cut) ? $item->cut : 0;
            switch (true) {
                case isset($item->bin):
                    $dumper->dumpString($cursor, $item->bin, true, $cut);

                    return;

                case isset($item->str):
                    $dumper->dumpString($cursor, $item->str, false, $cut);

                    return;

                case isset($item->count):
                    $dumper->enterArray($cursor, $item->count, !empty($item->indexed), $children, $cut);
                    $this->dumpChildren($dumper, $cursor, $refs, $item, $children, $cut, empty($item->indexed) ? $cursor::HASH_ASSOC : $cursor::HASH_INDEXED);
                    $dumper->leaveArray($cursor, $item->count, !empty($item->indexed), $children, $cut);

                    return;

                case isset($item->class):
                    $dumper->enterObject($cursor, $item->class, $children, $cut);
                    $this->dumpChildren($dumper, $cursor, $refs, $item, $children, $cut, $cursor::HASH_OBJECT);
                    $dumper->leaveObject($cursor, $item->class, $children, $cut);

                    return;

                case isset($item->res):
                    $dumper->enterResource($cursor, $item->res, $children, $cut);
                    $this->dumpChildren($dumper, $cursor, $refs, $item, $children, $cut, $cursor::HASH_RESOURCE);
                    $dumper->leaveResource($cursor, $item->res, $children, $cut);

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

    private function dumpChildren($dumper, $cursor, &$refs, $parent, $children, $hashCut, $hashType)
    {
        if ($children) {
            $cursor = clone $cursor;
            ++$cursor->depth;
            $cursor->hashType = $hashType;
            $cursor->hashIndex = 0;
            $cursor->hashLength = $children;
            $cursor->hashCut = $hashCut;
            foreach ($this->data[$parent->pos] as $cursor->hashKey => $child) {
                $this->dumpItem($dumper, $cursor, $refs, $child);
                ++$cursor->hashIndex;
            }
        }
    }
}
