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
 * PHP structure traversal, with length limits and a callback mechanism for getting detailed
 * information about objects and resources.
 *
 * For example and by default, resources of type stream are expanded by stream_get_meta_data,
 * those of type process by proc_get_status, and closures are associated with a method that
 * uses reflection to provide detailed information about anonymous functions. This class is
 * designed to implement these mechanisms in a way independent of the final representation.
 */
abstract class AbstractDumper extends Walker
{
    public

    $maxLength = 500;

    static

    $defaultOutputStream = 'php://output',
    $defaultCasters = array(
        'o:Closure'        => 'Patchwork\Dumper\Caster\BaseCaster::castClosure',
        'o:Doctrine\Common\Proxy\Proxy'
                           => 'Patchwork\Dumper\Caster\DoctrineCaster::castCommonProxy',
        'o:Doctrine\ORM\Proxy\Proxy'
                           => 'Patchwork\Dumper\Caster\DoctrineCaster::castOrmProxy',
        'o:ErrorException' => 'Patchwork\Dumper\Caster\ExceptionCaster::castErrorException',
        'o:Exception'      => 'Patchwork\Dumper\Caster\ExceptionCaster::castException',
        'o:Patchwork\Debug\InDepthRecoverableErrorException'
                           => 'Patchwork\Dumper\Caster\ExceptionCaster::castInDepthException',
        'o:Patchwork\Dumper\ThrowingCasterException'
                           => 'Patchwork\Dumper\Caster\ExceptionCaster::castThrowingCasterException',
        'o:PDO'            => 'Patchwork\Dumper\Caster\PdoCaster::castPdo',
        'o:PDOStatement'   => 'Patchwork\Dumper\Caster\PdoCaster::castPdoStatement',
        'o:Reflector'      => 'Patchwork\Dumper\Caster\BaseCaster::castReflector',
        'o:SplDoublyLinkedList' => 'Patchwork\Dumper\Caster\SplCaster::castSplDoublyLinkedList',
        'o:SplFixedArray'       => 'Patchwork\Dumper\Caster\SplCaster::castSplFixedArray',
        'o:SplHeap'             => 'Patchwork\Dumper\Caster\SplCaster::castIterator',
        'o:SplObjectStorage'    => 'Patchwork\Dumper\Caster\SplCaster::castSplObjectStorage',
        'o:SplPriorityQueue'    => 'Patchwork\Dumper\Caster\SplCaster::castIterator',

        'r:dba'            => 'Patchwork\Dumper\Caster\BaseCaster::castDba',
        'r:dba persistent' => 'Patchwork\Dumper\Caster\BaseCaster::castDba',
        'r:gd'             => 'Patchwork\Dumper\Caster\BaseCaster::castGd',
        'r:mysql link'     => 'Patchwork\Dumper\Caster\BaseCaster::castMysqlLink',
        'r:process'        => 'Patchwork\Dumper\Caster\BaseCaster::castProcess',
        'r:stream'         => 'Patchwork\Dumper\Caster\BaseCaster::castStream',
    );

    protected

    $line = '',
    $depth = 0,
    $dumpLength = 0,
    $hashPosition = 0,
    $reserved = array('_' => 1, '__cutBy' => 1, '__refs' => 1, '__proto__' => 1),
    $lineDumper = array(__CLASS__, 'echoLine'),
    $casters = array(),
    $outputStream;


    public function __construct($outputStream = null, array $defaultCasters = null)
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

    public function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback)
        {
            $this->casters[strtolower($type)][] = $callback;
        }
    }

    public function setLineDumper($callback)
    {
        $prev = $this->lineDumper;
        $this->lineDumper = $callback;

        return $prev;
    }

    public function walk(&$ref)
    {
        $this->line = '';
        $this->dumpLength =
            $this->hashPosition = 0;

        try {parent::walk($ref);}
        catch (\Exception $e) {}

        '' !== $this->line && $this->dumpLine(0);
        $this->dumpLine(false); // Notifies end of dump

        if (isset($e)) throw $e;
    }

    static function dump(&$a)
    {
        $d = new static;
        $d->walk($a);
    }


    protected function dumpObject($info)
    {
        $obj = $info['value'];

        if (method_exists($obj, '__debugInfo'))
        {
            $a = array();
            if (! $this->callCaster(array($this, '__debugInfo'), $obj, $a))
                $a = (array) $obj;
        }
        else $a = (array) $obj;

        $c = $info['object_class'];
        $p = array($c => $c)
            + class_parents($obj)
            + class_implements($obj)
            + array('*' => '*');

        foreach (array_reverse($p) as $p)
            if (! empty($this->casters[$p = 'o:' . strtolower($p)]))
                foreach ($this->casters[$p] as $p)
                    $this->callCaster($p, $obj, $a);

        $this->dumpHash($c, $a, count($a));
    }

    protected function dumpResource($info)
    {
        $type = $info['resource_type'];
        $a = array();
        $b = array();

        if (! empty($this->casters['r:' . $type]))
        {
            foreach ($this->casters['r:' . $type] as $c)
            {
                $this->callCaster($c, $info['value'], $b);
            }
        }

        foreach ($b as $b => $c)
            $a[strncmp($b, "\0~\0", 3) ? "\0~\0$b" : $b] = $c;

        $this->dumpHash("resource:{$type}", $a, count($a));
    }

    protected function callCaster($callback, $obj, &$a)
    {
        try
        {
            // Ignore invalid $callback
            $this->ignoreError = true;
            $callback = call_user_func($callback, $obj, $a);
            $this->ignoreError = false;

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

    protected function dumpHash($type, &$array, $len)
    {
        if (! $len) return array();

        $hashPosition = $this->hashPosition;
        $this->hashPosition = $this->position;

        ++$this->depth;
        $isArray = 'array' === $type;

        if (0 >= $this->maxLength) $len = -1;
        else if ($this->dumpLength >= $this->maxLength) $i = $max = $this->maxLength;
        else if ($len > 0)
        {
            $i = $this->dumpLength;
            $max = $this->maxLength;
            $this->dumpLength += $len;
            $len += $i - $max;
        }

        foreach ($array as $key => &$ref)
        {
            $k = $key;

            if ($len >= 0 && $i++ === $max)
            {
                if ($len)
                {
                    $this->dumpString('__cutBy', true);
                    $this->dumpScalar($len);
                }

                break;
            }
            else if (isset($k[0]) && "\0" === $k[0] && ! $isArray) $k = implode(':', explode("\0", substr($k, 1), 2));
            else if (isset($this->reserved[$k]) || false !== strpos($k, ':')) $k = ':' . $k;

            $this->dumpString($k, true);
            $this->walkRef($ref, $this->getInfo($ref, $array[$key]), $key);
        }

        $this->hashPosition = $hashPosition;

        if (--$this->depth) return array();

        return $this->cleanRefPools();
    }

    protected function dumpLine($depthOffset)
    {
        call_user_func($this->lineDumper, $this->line, $this->depth + $depthOffset);
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

    public function __construct($caster, \Exception $prev)
    {
        $this->caster = $caster;
        parent::__construct(null, 0, $prev);
    }
}
