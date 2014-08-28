<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

use Symfony\Component\VarDumper\Dumper\DumperInternalsInterface;
use Symfony\Component\VarDumper\Dumper\Cursor;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Data
{
    private $data;

    /**
     * @param array $data A array as returned by ClonerInterface::cloneVar().
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getRawData()
    {
        return $this->data;
    }

    /**
     * Dumps data with a DumperInternalsInterface dumper.
     */
    public function dump(DumperInternalsInterface $dumper)
    {
        $refs = array(0);
        $this->dumpItem($dumper, new Cursor, $refs, $this->data[0][0]);
    }

    /**
     * Breadth-first dumping of items.
     *
     * @param DumperInternalsInterface $dumper The dumper being used for dumping.
     * @param Cursor                   $cursor A cursor used for tracking dumper state position.
     * @param array                    &$refs  A map of all references discovered while dumping.
     * @param mixed                    $item   A Stub object or the original value being dumped.
     */
    private function dumpItem($dumper, $cursor, &$refs, $item)
    {
        $cursor->refIndex = $cursor->refTo = $cursor->refIsHard = false;

        if ($item instanceof Stub && Stub::TYPE_REF === $item->type) {
            if ($item->refs) {
                if (isset($refs[$r = $item->refs])) {
                    $cursor->refTo = $refs[$r];
                    $cursor->refIsHard = true;
                } else {
                    $cursor->refIndex = $refs[$r] = ++$refs[0];
                }
            }
            $item = $item->value;
        }
        if ($item instanceof Stub) {
            if ($item->refs) {
                if (isset($refs[$r = $item->refs])) {
                    if (false === $cursor->refTo) {
                        $cursor->refTo = $refs[$r];
                        $cursor->refIsHard = Stub::TYPE_ARRAY === $item->type;
                    }
                } elseif (false !== $cursor->refIndex) {
                    $refs[$r] = $cursor->refIndex;
                } else {
                    $cursor->refIndex = $refs[$r] = ++$refs[0];
                }
            }
            $cut = $item->cut;

            if ($item->position && false === $cursor->refTo) {
                $children = $this->data[$item->position];

                if ($cursor->stop) {
                    if ($cut >= 0) {
                        $cut += count($children);
                    }
                    $children = array();
                }
            } else {
                $children = array();
            }
            switch ($item->type) {
                case Stub::TYPE_STRING:
                    $dumper->dumpString($cursor, $item->value, Stub::STRING_BINARY === $item->class, $cut);

                    return;

                case Stub::TYPE_ARRAY:
                    $dumper->enterArray($cursor, $item->value, Stub::ARRAY_INDEXED === $item->class, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, $item->class);
                    $dumper->leaveArray($cursor, $item->value, Stub::ARRAY_INDEXED === $item->class, (bool) $children, $cut);

                    return;

                case Stub::TYPE_OBJECT:
                    $dumper->enterObject($cursor, $item->class, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, Cursor::HASH_OBJECT);
                    $dumper->leaveObject($cursor, $item->class, (bool) $children, $cut);

                    return;

                case Stub::TYPE_RESOURCE:
                    $dumper->enterResource($cursor, $item->class, (bool) $children);
                    $cut = $this->dumpChildren($dumper, $cursor, $refs, $children, $cut, Cursor::HASH_RESOURCE);
                    $dumper->leaveResource($cursor, $item->class, (bool) $children, $cut);

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

    /**
     * Dumps children of hash structures.
     *
     * @param DumperInternalsInterface $dumper
     * @param Cursor                   $parentCursor The cursor of the parent hash.
     * @param array                    &$refs        A map of all references discovered while dumping.
     * @param array                    $children     The children to dump.
     * @param int                      $hashCut      The number of items removed from the original hash.
     * @param string                   $hashType     A Cursor::HASH_* const.
     *
     * @return int The final number of removed items.
     */
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
     * Portable variant of utf8_encode()
     *
     * @param string $s
     *
     * @return string
     *
     * @internal
     */
    public static function utf8Encode($s)
    {
        if (function_exists('iconv')) {
            return iconv('CP1252', 'UTF-8', $s);
        }

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
