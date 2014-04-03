<?php

namespace Patchwork\Dumper;

use stdClass;

class CollectorDumper
{
    private $collector;
    private $dumper;

    public function __construct(Collector\CollectorInterface $collector, DumperInterface $dumper)
    {
        $this->collector = $collector;
        $this->dumper = $dumper;
    }

    public function dump($var)
    {
        $this->refs = array();
        $this->refsNb = 0;
        $this->queue = $this->collector->collect($var);

        $cursor = new Cursor();
        $this->dumpItem($this->queue[0][0], $cursor);

        $this->refs = array();
        $this->queue = array();
    }

    protected function dumpItem($item, $cursor)
    {
        $cursor->refIndex = $cursor->refTo = $cursor->refIsHard = false;
        $cursor->dumpedChildren = 0;

        if ($item instanceof stdClass) {
            if (isset($item->ref)) {
                if (isset($this->refs[$r = $item->ref])) {
                    $cursor->refTo = $this->refs[$r];
                    $cursor->refIsHard = isset($item->count) || isset($item->val);
                } else {
                    $cursor->refIndex = $this->refs[$r] = ++$this->refsNb;
                }
            }
            if (isset($item->val)) {
                $item = $item->val;
                if (isset($item->ref)) {
                    if (isset($this->refs[$r = $item->ref])) {
                        $cursor->refTo = $this->refs[$r];
                    } elseif (false === $cursor->refIndex) {
                        $cursor->refIndex = $this->refs[$r] = ++$this->refsNb;
                    } else {
                        $this->refs[$r] = $cursor->refIndex;
                    }
                }
            }
            if (isset($item->pos) && false === $cursor->refTo) {
                $cursor->dumpedChildren = count($this->queue[$item->pos]);
            }
            $cut = isset($item->cut) ? $item->cut : 0;
            switch (true) {
                case isset($item->bin):
                    $this->dumper->dumpString($cursor, $item->bin, true, $cut);
                    return;

                case isset($item->str):
                    $this->dumper->dumpString($cursor, $item->str, false, $cut);
                    return;

                case isset($item->count):
                    $this->dumper->enterArray($cursor, $item->count, $cut, !empty($item->indexed));
                    $cursor->dumpedChildren and $this->dumpChildren($item, $cursor, $cut, empty($item->indexed) ? $cursor::HASH_ASSOC : $cursor::HASH_INDEXED);
                    $this->dumper->leaveArray($cursor, $item->count, $cut, !empty($item->indexed));
                    return;

                case isset($item->class):
                    $this->dumper->enterObject($cursor, $item->class, $cut);
                    $cursor->dumpedChildren and $this->dumpChildren($item, $cursor, $cut, $cursor::HASH_OBJECT);
                    $this->dumper->leaveObject($cursor, $item->class, $cut);
                    return;

                case isset($item->res):
                    $this->dumper->enterResource($cursor, $item->res, $cut);
                    $cursor->dumpedChildren and $this->dumpChildren($item, $cursor, $cut, $cursor::HASH_RESOURCE);
                    $this->dumper->leaveResource($cursor, $item->res, $cut);
                    return;
            }
        }

        if ('array' === $type = gettype($item)) {
            $this->dumper->enterArray($cursor, 0, 0, true);
            $this->dumper->leaveArray($cursor, 0, 0, true);
        } else {
            $this->dumper->dumpScalar($cursor, $type, $item);
        }
    }

    protected function dumpChildren($parent, $cursor, $hashCut, $hashType)
    {
        $cursor = clone $cursor;
        ++$cursor->depth;
        $cursor->hashType = $hashType;
        $cursor->hashIndex = 0;
        $cursor->hashLength = $cursor->dumpedChildren;
        $cursor->hashCut = $hashCut;
        foreach ($this->queue[$parent->pos] as $cursor->hashKey => $child) {
            $this->dumpItem($child, $cursor);
            ++$cursor->hashIndex;
        }
    }
}
