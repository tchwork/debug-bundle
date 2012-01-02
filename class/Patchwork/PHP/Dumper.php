<?php /****************** vi: set fenc=utf-8 ts=4 sw=4 et: *****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/lgpl.txt GNU/LGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Lesser General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/

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

    $maxLength = 100,
    $maxDepth = 10;

    protected

    $depthLimited = array(),
    $reserved = array('_' => 1, '__cutBy' => 1, '__refs' => 1, '__proto__' => 1),
    $callbacks = array(
        'o:closure' => array(__CLASS__, 'castClosure'),
        'r:stream' => 'stream_get_meta_data',
        'r:process' => 'proc_get_status',
    );


    function setCallback($type, $callback)
    {
        $this->callbacks[strtolower($type)] = $callback;
    }

    protected function dumpObject($obj)
    {
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

        $this->walkHash($c, $a);
    }

    protected function dumpResource($res)
    {
        $h = get_resource_type($res);

        if (empty($this->callbacks['r:' . $h])) $res = array();
        else
        {
            try {$res = call_user_func($this->callbacks['r:' . $h], $res);}
            catch (\Exception $e) {$res = array();}
        }

        $this->walkHash("resource:{$h}", $res);
    }

    protected function dumpRef($is_soft, $ref_counter = null, &$ref_value = null)
    {
        if (null !== $ref_value && isset($this->depthLimited[$ref_counter]) && $this->depth !== $this->maxDepth)
        {
            unset($this->depthLimited[$ref_counter]);

            switch (true)
            {
            case is_resource($ref_value): $this->dumpResource($ref_value); return true;
            case is_object($ref_value): $this->dumpObject($ref_value); return true;
            case is_array($ref_value):
                $ref_counter = count($ref_value);
                isset($ref_value[$this->token]) && --$ref_counter;
                $this->walkHash('array:' . $ref_counter, $ref_value);
                return true;
            }
        }

        return false;
    }

    protected function walkHash($type, &$a)
    {
        $len = count($a);
        isset($a[$this->token]) && --$len;

        if ($len && $this->depth === $this->maxDepth && 0 < $this->maxDepth)
        {
            $this->depthLimited[$this->counter] = 1;

            if (isset($this->refPool[$this->counter]))
                $this->refPool[$this->counter]['ref_counter'] = $this->counter;

            $this->dumpString('__cutBy', true);
            $this->dumpScalar($len);
            $len = 0;
        }

        if (!$len) return array();

        $i = 0;
        ++$this->depth;
        if (false !== strpos($type, ':')) unset($type);

        foreach ($a as $k => &$a)
        {
            if ($k === $this->token) continue;
            else if ($i === $this->maxLength && 0 < $this->maxLength)
            {
                if ($len -= $i)
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
            ++$i;
        }

        if (--$this->depth) return array();
        else return $this->cleanRefPools();
    }

    static function castClosure($c)
    {
        $a = array();
        if (!class_exists('ReflectionFunction', false)) return $a;
        $c = new \ReflectionFunction($c);
        $c->returnsReference() && $a[] = '&';

        foreach ($c->getParameters() as $p)
        {
            $n = ($p->isPassedByReference() ? '&$' : '$') . $p->getName();

            if ($p->isDefaultValueAvailable()) $a[$n] = $p->getDefaultValue();
            else $a[] = $n;
        }

        $a['use'] = array();

        if (false === $a['file'] = $c->getFileName()) unset($a['file']);
        else $a['lines'] = $c->getStartLine() . '-' . $c->getEndLine();

        if (!$c = $c->getStaticVariables()) unset($a['use']);
        else foreach ($c as $p => &$c) $a['use']['$' . $p] =& $c;

        return $a;
    }
}
