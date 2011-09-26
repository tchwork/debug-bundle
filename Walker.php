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

abstract class Walker
{
    public

    $detectHardRefs = true;

    protected

    $tag,
    $token,
    $depth = 0,
    $counter = 0,
    $arrayType = 0,
    $refPool = array(),
    $valPool = array(),
    $objPool = array(),
    $linkPool = array(),
    $arrayPool = array();


    abstract protected function dumpRef($is_soft);
    abstract protected function dumpScalar($val);
    abstract protected function dumpString($str, $is_key);
    abstract protected function dumpObject($obj);
    abstract protected function dumpResource($res);


    function walk(&$a)
    {
        $this->token = ~mt_rand();
        $this->tag = array($this->token => (object) array());
        $this->counter = $this->depth = 0;
        $this->walkRef($a);
    }

    protected function walkRef(&$a)
    {
        ++$this->counter;

        if (is_array($a)) return $this->walkArray($a);

        $v = $a;

        if ($this->detectHardRefs && 1 < $this->counter)
        {
            $this->refPool[$this->counter] =& $a;
            $this->valPool[$this->counter] = $a;
            $a = $this->tag;
        }

        switch (true)
        {
        default: $this->dumpScalar($v); break;
        case is_string($v): $this->dumpString($v, false); break;

        case is_object($v): $h = pack('H*', spl_object_hash($v)); // no break;
        case is_resource($v): isset($h) || $h = (int) substr((string) $v, 13);

            if (isset($this->objPool[$h]))
            {
                $this->linkPool[$this->counter] = $this->objPool[$h];
                return $this->dumpRef($v);
            }
            else $this->objPool[$h] = $this->counter;

            $t = $this->arrayType;
            $this->arrayType = 0;
            if (isset($h[0])) $this->dumpObject($v);
            else $this->dumpResource($v);
            $this->arrayType = $t;
        }
    }

    protected function walkArray(&$a)
    {
        if (isset($a[$this->token]))
        {
            if ($a[$this->token] === $this->tag[$this->token]) $a[] = $this->counter;
            else $this->linkPool[$this->counter] = $a[$this->token];
            return $this->dumpRef(false);
        }

        if ($this->detectHardRefs) $token = $this->token;
        else
        {
/**/        if (PHP_VERSION_ID >= 50206)
/**/        {
                if (0 === $this->arrayType)
                {
                    // Detect recursive arrays by catching recursive count warnings
                    $this->arrayType = 1;
                    set_error_handler(array($this, 'catchRecursionWarning'));
                    count($a, COUNT_RECURSIVE);
                    restore_error_handler();
                }
                if (2 === $this->arrayType) $token = $this->token;
/**/        }
/**/        else
/**/        {
                $token = $this->token;
/**/        }
        }

        $len = count($a);

        if (isset($token))
        {
            $a[$token] = $this->counter;
            $this->arrayPool[] =& $a;
        }

        $this->walkHash('array:' . $len, $a);
    }

    protected function walkHash($type, &$a)
    {
        ++$this->depth;

        foreach ($a as $k => &$a)
        {
            if ($k === $this->token) continue;
            $this->dumpString($k, true);
            $this->walkRef($a);
        }

        if (--$this->depth) return array();
        else return $this->cleanRefPools();
    }

    protected function cleanRefPools()
    {
        $refs = array();

        foreach ($this->refPool as $k => &$v)
        {
            $len = $v;
            $v = $this->valPool[$k];
            isset($len[0]) && $refs[$k] = array_slice($len, 1);
        }

        $this->refPool = $this->valPool = $this->objPool = array();
        foreach ($this->linkPool as $len => $k) $refs[$k][] = $len;
        foreach ($this->arrayPool as &$a) unset($a[$this->token]);
        $this->arrayPool = $this->linkPool = array();

        return $refs;
    }

    function catchRecursionWarning()
    {
        $this->arrayType = 2;
    }
}
