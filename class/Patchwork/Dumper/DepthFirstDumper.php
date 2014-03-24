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
 * Depth first traversal, with max depth limits.
 */
abstract class DepthFirstDumper extends AbstractDumper
{
    public

    $maxDepth = 5;

    protected

    $depthLimited = array(),
    $objectsDepth = array();


    public function walk(&$ref)
    {
        try {parent::walk($ref);}
        catch (\Exception $e) {}

        $this->depthLimited = $this->objectsDepth = array();

        if (isset($e)) throw $e;
    }


    protected function dumpObject($info)
    {
        if (isset($this->objectsDepth[$info['object_hash']]))
        {
            if ($this->objectsDepth[$info['object_hash']] < $this->depth)
            {
                if (self::$tag === $tag = $this->refPool[$this->position])
                    $this->refPool[$this->position] = $tag = clone self::$tag;

                $tag->position = $this->position;
                $tag->info = $info;
                $this->dumpRef(true, $this->position, $info);

                return;
            }
            else unset($this->objectsDepth[$info['object_hash']]);
        }

        parent::dumpObject($info);
    }

    protected function dumpRef($isSoft, $position, $info)
    {
        if (! $position) return false;

        if (isset($info['object_hash']))
        {
            if (isset($this->objectsDepth[$info['object_hash']]) && $this->objectsDepth[$info['object_hash']] === $this->depth)
            {
                $this->dumpObject($info);
                return true;
            }
        }

        if (isset($this->depthLimited[$position]) && $this->depth < $this->maxDepth)
        {
            unset($this->depthLimited[$position]);

            if (null === $info) return false;

            if (isset($info['object_hash'])) $this->dumpObject($info);
            else if (isset($info['resource_id'])) $this->dumpResource($info);
            else $this->dumpHash('array', $info['value'], $info['array_count']);

            return true;
        }

        return false;
    }

    protected function dumpHash($type, &$array, $len)
    {
        if (! $len) return array();

        if ($this->depth >= $this->maxDepth && 0 < $this->maxDepth)
        {
            $this->depthLimited[$this->position] = 1;

            if ($this->refPool[$this->position] === self::$tag)
            {
                $this->refPool[$this->position] = clone self::$tag;
                $this->refPool[$this->position]->position = $this->position;
                $this->refPool[$this->position]->type = $this->position;
            }

            $this->dumpString('__cutBy', true);
            $this->dumpScalar($len);

            return array();
        }

        $l = $len;
        if (0 >= $this->maxLength) $l = -1;
        else if ($l > 0) $l += $this->dumpLength - $this->maxLength;

        if ($l < 0)
        {
            // Breadth-first for objects

            foreach ($array as $val)
            {
                switch (gettype($val))
                {
                case 'object':
                    if (! $l)
                    {
                        $h = pack('H*', spl_object_hash($val));
                        isset($this->objPool[$h]) or $this->objectsDepth += array($h => $this->depth+1);
                    }
                    // No break;
                case 'array': $l = 0;
                }
            }
        }

        $type = parent::dumpHash($type, $array, $len);

        while (end($this->objectsDepth) === $this->depth+1) array_pop($this->objectsDepth);

        return $type;
    }
}
