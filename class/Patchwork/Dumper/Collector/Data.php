<?php

namespace Patchwork\Dumper\Collector;

use stdClass;
use Patchwork\Dumper\DumperInterface;
use Patchwork\Dumper\Cursor;

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
        $this->dumpItem($dumper, new Cursor, $refs, $this->data[0][0]);
    }

    protected function dumpItem($dumper, $cursor, &$refs, $item)
    {
        $cursor->refIndex = $cursor->refTo = $cursor->refIsHard = false;
        $cursor->dumpedChildren = 0;

        if ($item instanceof stdClass) {
            if (isset($item->ref)) {
                if (isset($refs[$r = $item->ref])) {
                    $cursor->refTo = $refs[$r];
                    $cursor->refIsHard = isset($item->count) || isset($item->val);
                } else {
                    $cursor->refIndex = $refs[$r] = ++$refs[0];
                }
            }
            if (isset($item->val)) {
                $item = $item->val;
                if (isset($item->ref)) {
                    if (isset($refs[$r = $item->ref])) {
                        $cursor->refTo = $refs[$r];
                    } elseif (false === $cursor->refIndex) {
                        $cursor->refIndex = $refs[$r] = ++$refs[0];
                    } else {
                        $refs[$r] = $cursor->refIndex;
                    }
                }
            }
            if (isset($item->pos) && false === $cursor->refTo) {
                $cursor->dumpedChildren = count($this->data[$item->pos]);
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
                    $dumper->enterArray($cursor, $item->count, $cut, !empty($item->indexed));
                    $this->dumpChildren($dumper, $cursor, $refs, $item, $cut, empty($item->indexed) ? $cursor::HASH_ASSOC : $cursor::HASH_INDEXED);
                    $dumper->leaveArray($cursor, $item->count, $cut, !empty($item->indexed));
                    return;

                case isset($item->class):
                    $dumper->enterObject($cursor, $item->class, $cut);
                    $this->dumpChildren($dumper, $cursor, $refs, $item, $cut, $cursor::HASH_OBJECT);
                    $dumper->leaveObject($cursor, $item->class, $cut);
                    return;

                case isset($item->res):
                    $dumper->enterResource($cursor, $item->res, $cut);
                    $this->dumpChildren($dumper, $cursor, $refs, $item, $cut, $cursor::HASH_RESOURCE);
                    $dumper->leaveResource($cursor, $item->res, $cut);
                    return;
            }
        }

        if ('array' === $type = gettype($item)) {
            $dumper->enterArray($cursor, 0, 0, true);
            $dumper->leaveArray($cursor, 0, 0, true);
        } else {
            $dumper->dumpScalar($cursor, $type, $item);
        }
    }

    protected function dumpChildren($dumper, $cursor, &$refs, $parent, $hashCut, $hashType)
    {
        if ($cursor->dumpedChildren) {
            $cursor = clone $cursor;
            ++$cursor->depth;
            $cursor->hashType = $hashType;
            $cursor->hashIndex = 0;
            $cursor->hashLength = $cursor->dumpedChildren;
            $cursor->hashCut = $hashCut;
            foreach ($this->data[$parent->pos] as $cursor->hashKey => $child) {
                $this->dumpItem($dumper, $cursor, $refs, $child);
                ++$cursor->hashIndex;
            }
        }
    }
}
