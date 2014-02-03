<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
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

    static

    $defaultCasters = array(
        'o:Closure'        => array('Patchwork\PHP\Dumper\BaseCaster', 'castClosure'),
        'o:Reflector'      => array('Patchwork\PHP\Dumper\BaseCaster', 'castReflector'),
        'o:PDO'            => array('Patchwork\PHP\Dumper\PdoCaster', 'castPdo'),
        'o:PDOStatement'   => array('Patchwork\PHP\Dumper\PdoCaster', 'castPdoStatement'),
        'o:Exception'      => array('Patchwork\PHP\Dumper\ExceptionCaster', 'castException'),
        'o:ErrorException' => array('Patchwork\PHP\Dumper\ExceptionCaster', 'castErrorException'),
        'o:Patchwork\PHP\InDepthRecoverableErrorException'
                           => array('Patchwork\PHP\Dumper\ExceptionCaster', 'castInDepthException'),
        'o:Doctrine\ORM\Proxy\Proxy'
                           => array('Patchwork\PHP\Dumper\DoctrineCaster', 'castOrmProxy'),
        'o:Doctrine\Common\Proxy\Proxy'
                           => array('Patchwork\PHP\Dumper\DoctrineCaster', 'castCommonProxy'),
        'r:dba'            => array('Patchwork\PHP\Dumper\BaseCaster', 'castDba'),
        'r:dba persistent' => array('Patchwork\PHP\Dumper\BaseCaster', 'castDba'),
        'r:process'        => array('Patchwork\PHP\Dumper\BaseCaster', 'castProcess'),
        'r:stream'         => array('Patchwork\PHP\Dumper\BaseCaster', 'castStream'),
    );

    protected

    $line = '',
    $lines = array(),
    $lastHash = 0,
    $dumpLength = 0,
    $depthLimited = array(),
    $objectsDepth = array(),
    $reserved = array('_' => 1, '__cutBy' => 1, '__refs' => 1, '__proto__' => 1),
    $lineDumper = array(__CLASS__, 'echoLine'),
    $casters = array();


    function __construct(array $defaultCasters = null)
    {
        $this->setLineDumper(array($this, 'stackLine'));
        isset($defaultCasters) or $defaultCasters = static::$defaultCasters;
        $this->addCasters($defaultCasters);
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

        if (isset($e)) throw $e;

        $lines = implode("\n", $this->lines);
        $this->lines = array();

        return $lines;
    }

    static function dump(&$a)
    {
        $d = new static;
        $d->setLineDumper(array(get_called_class(), 'echoLine'));
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

        $a = (array) $obj;
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
                    try {$a = call_user_func($p, $obj, $a);}
                    catch (\Exception $e) {}
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
                try {$b = call_user_func($c, $res, $b);}
                catch (\Exception $e) {}
            }
        }

        foreach ($b as $b => $c) $a["\0~\0$b"] = $c;

        $this->walkHash("resource:{$type}", $a, count($a));
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

    protected function stackLine($line, $depth)
    {
        $this->lines[] = str_repeat('  ', $depth) . $line;
    }

    protected static function echoLine($line, $depth)
    {
        static $stderr;
        isset($stderr) or $stderr = fopen('php://stderr', 'wb');
        fwrite($stderr, str_repeat('  ', $depth) . $line . "\n");
    }
}
