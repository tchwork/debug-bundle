<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\PHP;

/**
 * Dumper extends Walker and adds managing depth and length limits, alongside with
 * a callback mechanism for getting detailed information about objects and resources.
 *
 * For example and by default, resources of type stream are expanded by stream_get_meta_data,
 * those of type process by proc_get_status, and closures are associated with a method that
 * uses reflection to provide detailed information about anonymous functions. This class is
 * designed to implement these mechanisms in a way independent of the final representation.
 */
abstract class Dumper extends Walker
{
    public

    $maxLength = 1000,
    $maxDepth = 10;

    protected

    $dumpLength = 0,
    $depthLimited = array(),
    $objectsDepth = array(),
    $reserved = array('_' => 1, '__cutBy' => 1, '__refs' => 1, '__proto__' => 1),
    $callbacks = array(
        'o:pdo' => array('Patchwork\PHP\Dumper\Caster', 'castPdo'),
        'o:pdostatement' => array('Patchwork\PHP\Dumper\Caster', 'castPdoStatement'),
        'o:closure' => array('Patchwork\PHP\Dumper\Caster', 'castClosure'),
        'o:reflector' => array('Patchwork\PHP\Dumper\Caster', 'castReflector'),
        'r:stream' => 'stream_get_meta_data',
        'r:process' => 'proc_get_status',
        'r:dba persistent' => array('Patchwork\PHP\Dumper\Caster', 'castDba'),
        'r:dba' => array('Patchwork\PHP\Dumper\Caster', 'castDba'),
    );


    function setCallback($type, $callback)
    {
        $this->callbacks[strtolower($type)] = $callback;
    }

    function walk(&$a)
    {
        try {parent::walk($a);}
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
                $this->refPool[$this->counter]['ref_counter'] = $this->counter;
                $this->dumpRef(true, $this->counter, $obj, 'object');
                return;
            }
            else unset($this->objectsDepth[$hash]);
        }

        $c = get_class($obj);
        $p = array($c => $c)
            + class_parents($obj)
            + class_implements($obj)
            + array('*' => '*');

        foreach ($p as $p)
        {
            if (isset($this->callbacks[$p = 'o:' . strtolower($p)]))
            {
                if (!$p = $this->callbacks[$p]) $a = array();
                else
                {
                    try {$a = call_user_func($p, $obj);}
                    catch (\Exception $e) {unset($a); continue;}
                }
                break;
            }
        }

        isset($a) || $a = (array) $obj;

        $this->walkHash($c, $a, count($a));
    }

    protected function dumpResource($res)
    {
        $h = get_resource_type($res);
        $a = array();

        if (! empty($this->callbacks['r:' . $h]))
        {
            try {$res = call_user_func($this->callbacks['r:' . $h], $res);}
            catch (\Exception $e) {$res = array();}

            foreach ($res as $k => $v)
                $a["\0~\0" . $k] = $v;
        }

        $this->walkHash("resource:{$h}", $a, count($a));
    }

    protected function dumpRef($is_soft, $ref_counter = null, &$ref_value = null, $ref_type = null)
    {
        if (null === $ref_value) return false;

        if ('object' === $ref_type)
        {
            $h = pack('H*', spl_object_hash($ref_value));

            if (isset($this->objectsDepth[$h]) && $this->objectsDepth[$h] === $this->depth)
            {
                $this->dumpObject($ref_value, $h);
                return true;
            }
        }

        if (isset($this->depthLimited[$ref_counter]) && $this->depth < $this->maxDepth)
        {
            unset($this->depthLimited[$ref_counter]);

            switch ($ref_type)
            {
            case 'object':
                $this->dumpObject($ref_value, $h);
                return true;
            case 'array':
                $ref_counter = $this->count($ref_value);
                isset($ref_value[self::$token]) && --$ref_counter;
                $this->walkHash('array:' . $ref_counter, $ref_value, $ref_counter);
                return true;
            case 'resource':
                $this->dumpResource($ref_value);
                return true;
            }
        }

        return false;
    }

    protected function walkHash($type, &$a, $len)
    {
        if ($len && $this->depth >= $this->maxDepth && 0 < $this->maxDepth)
        {
            $this->depthLimited[$this->counter] = 1;

            if (isset($this->refPool[$this->counter][self::$token]))
                $this->refPool[$this->counter]['ref_counter'] = $this->counter;

            $this->dumpString('__cutBy', true);
            $this->dumpScalar($len);
            $len = 0;
        }

        if (!$len) return array();

        ++$this->depth;
        if (0 === strncmp($type, 'array:', 6)) unset($type);

        if (0 >= $this->maxLength) $len = -1;
        else if ($this->dumpLength >= $this->maxLength) $i = $max = $this->maxLength;
        else
        {
            $i = $this->dumpLength;
            $max = $this->maxLength;
            $this->dumpLength += $len;
            $len += $i - $max;
        }

        if ($len < 0)
        {
            // Breadth-first for objects

            foreach ($a as &$k)
            {
                switch ($this->gettype($k))
                {
                case 'object':
                    if (! $len)
                    {
                        $h = pack('H*', spl_object_hash($k));
                        isset($this->objPool[$h]) or $this->objectsDepth += array($h => $this->depth);
                    }
                    // No break;
                case 'array': $len = 0;
                }
            }

            unset($k);
            $len = -1;
        }

        foreach ($a as $k => &$a)
        {
            if ($k === self::$token) continue;
            else if ($len >= 0 && $i++ === $max)
            {
                if ($len)
                {
                    $this->dumpString('__cutBy', true);
                    $this->dumpScalar($len);
                }

                break;
            }
            else if (isset($type, $k[0]) && "\0" === $k[0]) $k = implode(':', explode("\0", substr($k, 1), 2));
            else if (isset($this->reserved[$k]) || false !== strpos($k, ':')) $k = ':' . $k;

            $this->dumpString($k, true);
            $this->walkRef($a);
        }

        while (end($this->objectsDepth) === $this->depth) array_pop($this->objectsDepth);

        if (--$this->depth) return array();
        $this->depthLimited = array();
        return $this->cleanRefPools();
    }
}
