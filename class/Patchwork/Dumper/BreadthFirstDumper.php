<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper;

/**
 * Breadth first traversal.
 */
abstract class BreadthFirstDumper extends AbstractDumper
{
    protected $breadthQueue;

    public function walk(&$ref)
    {
        $this->breadthQueue = new \ArrayObject;

        try {parent::walk($ref);}
        catch (\Exception $e) {}

        $this->breadthQueue = null;

        if (isset($e)) throw $e;
    }

    protected function walkRef(&$ref, $info, $key)
    {
        if (1 < $this->depth)
        {
            if ($info instanceof WalkerRefTag)
            {
                return parent::walkRef($ref, $info, $key);
            }
            elseif (isset($info['object_hash']))
            {
                $h = $info['object_hash'];
            }
            else if (isset($info['resource_id']))
            {
                $h = $info['resource_id'];
            }
            else if (empty($info['array_count']))
            {
                return parent::walkRef($ref, $info, $key);
            }
            else
            {
                $h = null;
            }

            $key = ++$this->position;
            $this->refPool[$key] =& $ref;
            $this->valPool[$key] = $info['value'];
            ($ref instanceof WalkerRefTag and $ref->tag === self::$tag) or $ref = self::$tag;

            if (isset($this->objPool[$h]))
            {
                $this->dumpRef(true, $this->refMap[$key] = $this->objPool[$h], $info);
            }
            else
            {
                $this->breadthQueue[$key] = $info;
                $this->dumpRef(false, 0, null);
            }
        }
        else if (1 === $this->depth)
        {
            parent::walkRef($this->refPool[$key], $info, $key);
        }
        else
        {
            $key = ++$this->position + 1;
            $this->breadthQueue[$key] = $info;
            $this->refPool[$key] =& $ref;
            $this->valPool[$key] = $info['value'];
            $this->dumpHash(':', $this->breadthQueue, -1);
        }
    }

    protected function dumpRef($isSoft, $position, $info)
    {
        return false;
    }

    protected function getInfo(&$ref, $val)
    {
        if (1 === $this->depth) return $val;

        return parent::getInfo($ref, $val);
    }
}
