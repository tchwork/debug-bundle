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
 * Walker implements a mechanism to generically traverse any PHP variable.
 *
 * It takes internal references into account, recursive or non-recursive, without preempting any
 * special use of the discovered data. It exposes only one public method ->walk(), which triggers
 * the traversal. It also has a public property ->checkInternalRefs set to true by default, to
 * disable the check for internal references if the mechanism is considered too expensive.
 * Checking recursive references and object/resource can not be disabled but is much lighter.
 */
abstract class Walker
{
    public

    $checkInternalRefs = true;

    protected

    $depth = 0,
    $counter = 0,
    $arrayType = 0,
    $refMap = array(),
    $refPool = array(),
    $valPool = array(),
    $objPool = array(),
    $arrayPool = array();

    protected static

    $tag,
    $token;

    abstract protected function dumpRef($is_soft, $ref_counter = null, &$ref_value = null, $ref_type = null);
    abstract protected function dumpScalar($val);
    abstract protected function dumpString($str, $is_key);
    abstract protected function dumpObject($obj);
    abstract protected function dumpResource($res);


    function walk(&$a)
    {
        if (empty(self::$tag))
        {
            self::$tag = (object) array();
            self::$token = md5(mt_rand() . spl_object_hash(self::$tag), true);
            self::$tag = array(self::$token => self::$tag);
        }

        $this->arrayType = $this->counter = $this->depth = 0;
        $this->walkRef($a);
    }

    protected function walkRef(&$a)
    {
        ++$this->counter;

        $v = $a;

        if ('array' === $t = gettype($v))
        {
            unset($v);
            return $this->walkArray($a);
        }

        if ($this->checkInternalRefs && 1 < $this->counter)
        {
            $this->refPool[$this->counter] =& $a;
            $this->valPool[$this->counter] = $v;
            $a = self::$tag;
        }

        switch ($t)
        {
        default: $this->dumpScalar($v); break;
        case 'string': $this->dumpString($v, false); break;

        case 'object': $h = pack('H*', spl_object_hash($v)); // no break;
        case 'unknown type': // See http://php.net/is_resource#103942
        case 'resource': isset($h) || $h = (int) substr((string) $v, 13);

            if (empty($this->objPool[$h])) $this->objPool[$h] = $this->counter;
            else return $this->dumpRef(true, $this->refMap[$this->counter] = $this->objPool[$h], $v, $t);

            $t = $this->arrayType;
            $this->arrayType = 0;
            if (isset($h[0])) $this->dumpObject($v);
            else $this->dumpResource($v);
            $this->arrayType = $t;
        }
    }

    protected function walkArray(&$a)
    {
        if (isset($a[self::$token]))
        {
            $t = 'array';

            if (self::$tag[self::$token] === $c = $a[self::$token])
            {
                if (empty($a['ref_counter']))
                {
                    $a[] = -$this->counter;
                    return $this->dumpRef(false);
                }

                $c = $a['ref_counter'];
                $t = gettype($this->valPool[$c]);
                $a =& $this->valPool[$c];
            }

            $this->refMap[-$this->counter] = $c;
            return $this->dumpRef(false, $c, $a, $t);
        }

        if ($this->checkInternalRefs) $token = self::$token;
        else
        {
/**/        if (PHP_VERSION_ID >= 50206)
/**/        {
                if (0 === $this->arrayType)
                {
                    // Detect recursive arrays by catching recursive count warnings
                    $this->arrayType = 1;
                    set_error_handler(array($this, 'catchRecursionWarning'));
                    count(array(&$a), COUNT_RECURSIVE);
                    restore_error_handler();
                }

                if (2 === $this->arrayType) $token = self::$token;
/**/        }
/**/        else
/**/        {
                $token = self::$token;
/**/        }
        }

        $len = $this->count($a);

        if (isset($token))
        {
            $a[$token] = $this->counter;
            $this->arrayPool[] =& $a;
        }

        $this->walkHash('array:' . $len, $a, $len);
    }

    protected function walkHash($type, &$a, $len)
    {
        ++$this->depth;

        foreach ($a as $k => &$a)
        {
            if ($k === self::$token) continue;
            $this->dumpString($k, true);
            $this->walkRef($a);
        }

        if (--$this->depth) return array();
        else return $this->cleanRefPools();
    }

    protected function cleanRefPools()
    {
        $refs = array();

        set_error_handler('var_dump', 0);
        $e = error_reporting(0);

        foreach ($this->refPool as $k => &$v)
        {
            if (null !== array_splice($v, 0, 0) && isset($v[0], $v[self::$token]))
            {
                unset($v['ref_counter']);
                $refs[$k] = $v;
                array_splice($refs[$k], 0, 1);
            }

            $v = $this->valPool[$k];
        }

        error_reporting($e);
        restore_error_handler();

        $this->refPool = $this->valPool = $this->objPool = array();
        foreach ($this->refMap as $a => $k) $refs[$k][] = $a;
        foreach ($this->arrayPool as &$v) unset($v[self::$token]);
        $this->arrayPool = $this->refMap = array();

        return $refs;
    }

    protected function catchRecursionWarning()
    {
        $this->arrayType = 2;
    }

    protected function count(&$a)
    {
        $len = 0;
        foreach ($a as &$a) ++$len;
        return $len;
    }
}
