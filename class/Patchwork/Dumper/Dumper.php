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

    static

    $defaultOutputStream = 'php://output',
    $defaultCasters = array(
        'o:Closure'        => array('Patchwork\Dumper\Caster\BaseCaster', 'castClosure'),
        'o:Doctrine\Common\Proxy\Proxy'
                           => array('Patchwork\Dumper\Caster\DoctrineCaster', 'castCommonProxy'),
        'o:Doctrine\ORM\Proxy\Proxy'
                           => array('Patchwork\Dumper\Caster\DoctrineCaster', 'castOrmProxy'),
        'o:ErrorException' => array('Patchwork\Dumper\Caster\ExceptionCaster', 'castErrorException'),
        'o:Exception'      => array('Patchwork\Dumper\Caster\ExceptionCaster', 'castException'),
        'o:Patchwork\Debug\InDepthRecoverableErrorException'
                           => array('Patchwork\Dumper\Caster\ExceptionCaster', 'castInDepthException'),
        'o:Patchwork\Dumper\ThrowingCasterException'
                           => array('Patchwork\Dumper\Caster\ExceptionCaster', 'castThrowingCasterException'),
        'o:PDO'            => array('Patchwork\Dumper\Caster\PdoCaster', 'castPdo'),
        'o:PDOStatement'   => array('Patchwork\Dumper\Caster\PdoCaster', 'castPdoStatement'),
        'o:Reflector'      => array('Patchwork\Dumper\Caster\BaseCaster', 'castReflector'),
        'o:SplDoublyLinkedList' => array('Patchwork\Dumper\Caster\SplCaster', 'castSplDoublyLinkedList'),
        'o:SplFixedArray'       => array('Patchwork\Dumper\Caster\SplCaster', 'castSplFixedArray'),
        'o:SplHeap'             => array('Patchwork\Dumper\Caster\SplCaster', 'castIterator'),
        'o:SplObjectStorage'    => array('Patchwork\Dumper\Caster\SplCaster', 'castSplObjectStorage'),
        'o:SplPriorityQueue'    => array('Patchwork\Dumper\Caster\SplCaster', 'castIterator'),

        'r:dba'            => array('Patchwork\Dumper\Caster\BaseCaster', 'castDba'),
        'r:dba persistent' => array('Patchwork\Dumper\Caster\BaseCaster', 'castDba'),
        'r:gd'             => array('Patchwork\Dumper\Caster\BaseCaster', 'castGd'),
        'r:mysql link'     => array('Patchwork\Dumper\Caster\BaseCaster', 'castMysqlLink'),
        'r:process'        => array('Patchwork\Dumper\Caster\BaseCaster', 'castProcess'),
        'r:stream'         => array('Patchwork\Dumper\Caster\BaseCaster', 'castStream'),
    );

    protected

    $line = '',
    $lastHash = 0,
    $dumpLength = 0,
    $depthLimited = array(),
    $objectsDepth = array(),
    $reserved = array('_' => 1, '__cutBy' => 1, '__refs' => 1, '__proto__' => 1),
    $lineDumper = array(__CLASS__, 'echoLine'),
    $casters = array(),
    $outputStream;


    function __construct($outputStream = null, array $defaultCasters = null)
    {
        isset($defaultCasters) or $defaultCasters = static::$defaultCasters;
        $this->addCasters($defaultCasters);

        if (is_callable($outputStream))
        {
            $this->setLineDumper($outputStream);
        }
        else
        {
            isset($outputStream) or $outputStream =& static::$defaultOutputStream;
            if (is_string($outputStream)) $outputStream = fopen($outputStream, 'wb');
            $this->outputStream = $outputStream;
            $this->setLineDumper(array($this, 'echoLine'));
        }
    }

    function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback)
        {
            $this->casters[strtolower($type)][] = $callback;
        }
    }

    function setLineDumper($callback)
    {
        $prev = $this->lineDumper;
        $this->lineDumper = $callback;

        return $prev;
    }

    function walk(&$a)
    {
        $this->line = '';
        $this->lastHash = 0;

        try {parent::walk($a);}
        catch (\Exception $e) {}

        $this->depthLimited = $this->objectsDepth = array();
        '' !== $this->line && $this->dumpLine(0);
        $this->dumpLine(false); // Notifies end of dump

        if (isset($e)) throw $e;
    }

    static function dump(&$a)
    {
        $d = new static;
        $d->walk($a);
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

        if (method_exists($obj, '__debugInfo'))
        {
            $a = array();
            if (! $this->callCast(array($this, '__debugInfo'), $obj, $a))
                $a = (array) $obj;
        }
        else $a = (array) $obj;

        $c = get_class($obj);
        $p = array($c => $c)
            + class_parents($obj)
            + class_implements($obj)
            + array('*' => '*');

        foreach (array_reverse($p) as $p)
        {
            if (! empty($this->casters[$p = 'o:' . strtolower($p)]))
            {
                foreach ($this->casters[$p] as $p)
                {
                    $this->callCast($p, $obj, $a);
                }
            }
        }

        $this->walkHash($c, $a, count($a));
    }

    protected function dumpResource($res)
    {
        $type = get_resource_type($res);
        $a = array();
        $b = array();

        if (! empty($this->casters['r:' . $type]))
        {
            foreach ($this->casters['r:' . $type] as $c)
            {
                $this->callCast($c, $res, $b);
            }
        }

        foreach ($b as $b => $c)
            $a[strncmp($b, "\0~\0", 3) ? "\0~\0$b" : $b] = $c;

        $this->walkHash("resource:{$type}", $a, count($a));
    }

    protected function callCast($callback, $obj, &$a)
    {
        try
        {
            // Ignore invalid $callback
            $this->lastErrorMessage = true;
            $callback = call_user_func($callback, $obj, $a);
            $this->lastErrorMessage = false;

            if (is_array($callback))
            {
                $a = $callback;
                return true;
            }
        }
        catch (\Exception $e)
        {
            $a["\0~\0⚠"] = new ThrowingCasterException($callback, $e);
        }
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
        $lastHash = $this->lastHash;
        $this->lastHash = $this->counter;

        if ($len && $this->depth >= $this->maxDepth && 0 < $this->maxDepth)
        {
            $this->depthLimited[$this->counter] = 1;

            if (isset($this->refPool[$this->counter][self::$token]))
                $this->refPool[$this->counter]['ref_counter'] = $this->counter;

            $this->dumpString('__cutBy', true);
            $this->dumpScalar($len);
            $len = 0;
        }

        if (! $len)
        {
            $this->lastHash = $lastHash;

            return array();
        }

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

        $this->lastHash = $lastHash;

        if (--$this->depth) return array();
        $this->depthLimited = array();
        return $this->cleanRefPools();
    }

    protected function dumpLine($depth_offset)
    {
        call_user_func($this->lineDumper, $this->line, $this->depth + $depth_offset);
        $this->line = '';
    }

    protected function echoLine($line, $depth)
    {
        fwrite($this->outputStream, str_repeat('  ', $depth) . $line . "\n");
    }
}

class ThrowingCasterException extends \Exception
{
    private $caster;

    function __construct($caster, \Exception $prev)
    {
        $this->caster = $caster;
        parent::__construct(null, 0, $prev);
    }
}
