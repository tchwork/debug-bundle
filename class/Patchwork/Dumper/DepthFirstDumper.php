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


    protected function dumpObject($obj, $hash)
    {
        if (isset($this->objectsDepth[$hash]))
        {
            if ($this->objectsDepth[$hash] < $this->depth)
            {
                if (self::$tag === $obj = $this->refPool[$this->position])
                    $this->refPool[$this->position] = $obj = clone self::$tag;

                $obj->position = $this->position;
                $obj->hash = $hash;
                $this->dumpRef(true, $this->position, $hash);

                return;
            }
            else unset($this->objectsDepth[$hash]);
        }

        parent::dumpObject($obj, $hash);
    }

    protected function dumpRef($isSoft, $position, $hash)
    {
        if (! $position) return false;

        if (isset($hash[0]))
        {
            if (isset($this->objectsDepth[$hash]) && $this->objectsDepth[$hash] === $this->depth)
            {
                $this->dumpObject($this->valPool[$position], $hash);
                return true;
            }
        }

        if (isset($this->depthLimited[$position]) && $this->depth < $this->maxDepth)
        {
            unset($this->depthLimited[$position]);

            if (null === $hash) return false;

            if (isset($hash[0])) $this->dumpObject($this->valPool[$position], $hash);
            else if ($hash) $this->dumpResource($this->valPool[$position]);
            else $this->dumpHash('array', $this->valPool[$position]);

            return true;
        }

        return false;
    }

    protected function dumpHash($type, $array)
    {
        $len = count($array);

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

        if (0 >= $this->maxLength) $len = -1;
        else $len += $this->dumpLength - $this->maxLength;

        if ($len < 0)
        {
            // Breadth-first for objects

            foreach ($array as $val)
            {
                switch (gettype($val))
                {
                case 'object':
                    if (! $len)
                    {
                        $h = pack('H*', spl_object_hash($val));
                        isset($this->objPool[$h]) or $this->objectsDepth += array($h => $this->depth+1);
                    }
                    // No break;
                case 'array': $len = 0;
                }
            }
        }

        $type = parent::dumpHash($type, $array);

        while (end($this->objectsDepth) === $this->depth+1) array_pop($this->objectsDepth);

        return $type;
    }
}
