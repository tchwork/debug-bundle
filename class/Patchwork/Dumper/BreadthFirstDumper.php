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

        case 'array' === $type:
        case 'object' === $type:
        case 'resource' === $type:
        case 'unknown type' === $type:
            $key = ++$this->position;
            $this->breadthQueue[$key] = $type;
            $this->refPool[$key] =& $ref;
            $this->valPool[$key] = $val;
            $ref = self::$tag;
            $this->dumpRef(false, 0, null);
        }
        else if (1 === $this->depth)
        {
            parent::walkRef($this->refPool[$key], $this->valPool[$key], $val, $key);
        }
        else
        {
            $key = 1 + ++$this->position;
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
