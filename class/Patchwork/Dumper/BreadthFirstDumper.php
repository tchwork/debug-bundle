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

    protected function walkRef(&$ref, $val, $type, $key)
    {
        if (1 < $this->depth) switch (true)
        {
        default:
        case 'string' === $type:
        case 'integer' === $type:
        case 'array' === $type && empty($val):
        case $val instanceof WalkerRefTag && $val->tag === self::$tag:
            return parent::walkRef($ref, $val, $type, $key);

        case 'object' === $type: $h = pack('H*', spl_object_hash($val)); // No break;
        case 'unknown type' === $type:
        case 'resource' === $type: isset($h) or $h = (int) substr((string) $val, 13);
        case 'array' === $type: isset($h) or $h = null;
            $key = ++$this->position;
            $this->refPool[$key] =& $ref;
            $this->valPool[$key] = $val;
            $ref = self::$tag;

            if (isset($this->objPool[$h]))
            {
                $this->dumpRef(true, $this->refMap[$key] = $this->objPool[$h], $h);
            }
            else
            {
                $this->breadthQueue[$key] = $type;
                $this->dumpRef(false, 0, null);
            }
        }
        else if (1 === $this->depth)
        {
            parent::walkRef($this->refPool[$key], $this->valPool[$key], $val, $key);
        }
        else
        {
            $key = ++$this->position + 1;
            $this->breadthQueue[$key] = $type;
            $this->refPool[$key] =& $ref;
            $this->valPool[$key] = $val;
            $this->dumpHash('breadthQueue:', $this->breadthQueue);
        }
    }

    protected function dumpRef($isSoft, $position, $hash)
    {
        return false;
    }
}
