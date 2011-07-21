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

class Dumper
{
    public

    $maxLength = 100,
    $maxDepth  = 10;

    protected

    $lines,
    $callback,
    $token,
    $depth,
    $refCount,
    $resStack = array(),
    $arrayStack = array(),
    $objectStack = array(),
    $callbacks = array(
        'o:closure' => array(__CLASS__, 'castClosure'),
        'r:stream'  => 'stream_get_meta_data',
    );


    static function dumpConst($a)
    {
        return self::dump($a, false);
    }

    static function dump(&$a, $ref = true)
    {
        $d = new self;
        $d->dumpLines($a, $ref);
        return implode('', $d->lines);
    }

    function dumpLines(&$a, $ref = true)
    {
        $this->token = "\x9D" . md5(mt_rand(), true);
        $this->refCount = $this->depth = 0;

        $line = '';
        $this->refDump($line, $a, $ref ? '1' : '');
        '' !== $line && $this->dumpLine($line . "\n");

        foreach ($this->arrayStack as &$a) unset($a[$this->token]);

        $this->resStack = $this->arrayStack = $this->objectStack = array();
    }

    function setCallback($type, $callback)
    {
        $this->callbacks[strtolower($type)] = $callback;
    }

    protected function dumpLine($line)
    {
        $this->lines[] = $line;
    }

    protected function refDump(&$line, &$a, $ref = '1')
    {
        switch (true)
        {
        case true  === $a: $line .= 'true';  return;
        case false === $a: $line .= 'false'; return;
        case null  === $a: $line .= 'null';  return;
        case  INF  === $a: $line .= 'INF';   return;
        case -INF  === $a: $line .= '-INF';  return;
        case NAN   === $a: $line .= 'NAN';   return;

        case is_string($a):   $this->dumpString($line, $a);      return;
        case is_array($a):    $this->dumpArray($line, $a, $ref); return;
        case is_object($a):   $this->dumpObject($line, $a);      return;
        case is_resource($a): $this->dumpResource($line, $a);    return;

        // float and integer
        default: $line .= (string) $a;
        }
    }

    protected function dumpString(&$line, $a)
    {
        if (false !== $j = strpos($a, "\n"))
        {
            $i = 0;
            $line .= '"""' . "\n";
            $this->dumpLine($line);

            $pre = str_repeat('  ', $this->depth+1);

            do
            {
                $line = $pre . addcslashes(substr($a, $i, $j - $i + 1), '\\"');
                $this->dumpLine($line);
                $i = $j + 1;
            }
            while (false !== $j = strpos($a, "\n", $i));

            $line = $pre . addcslashes(substr($a, $i), '\\"') . '"""';
        }
        else $line .= '"' . addcslashes($a, '\\"') . '"';
    }

    protected function dumpArray(&$line, &$a, $ref, $open = '[', $close = ']')
    {
        if (!$a) return $line .= $open . $close;

        if ($ref)
        {
            if (isset($a[$this->token])) return $line .= "{$open}#{$a[$this->token]}{$close}";
            $a[$this->token] = ++$this->refCount;
            $ref = '#' . $a[$this->token];
            $this->arrayStack[] =& $a;
        }

        $line .= $ref . $open;

        if ($this->depth === $this->maxDepth)
        {
            $line .= '...' . $close;
            return;
        }

        ++$this->depth;
        $i = $j = 0;
        $pre = str_repeat('  ', $this->depth);

        foreach ($a as $k => &$v)
        {
            if ($this->token === $k) continue;

            $this->dumpLine($line . "\n");
            $line = $pre;

            if ($j === $this->maxLength)
            {
                $line .= '...';
                break;
            }
            else if (is_int($k) && 0 <= $k)
            {
                $k !== $i && $line .= $k . ' => ';
                $i = $k + 1;
            }
            else
            {
                if ('' === $ref)
                {
                    if (isset($k[0]) && "\0" === $k[0]) $k = implode(':', explode("\0", substr($k, 1), 2));
                    else if (false !== strpos($k, ':')) $k = ':' . $k;
                }

                $this->refDump($line, $k);
                $line .= ' => ';
            }

            $this->refDump($line, $v);
            ++$j;
        }

        $this->dumpLine($line . "\n");
        $line = substr($pre, 0, -2) . $close;
        --$this->depth;
    }

    protected function dumpObject(&$line, $a)
    {
        $h = spl_object_hash($a);
        $c = get_class($a);
        $line .= 'stdClass' !== $c ? $c : '';

        if (isset($this->objectStack[$h])) return $line .= '{#' . $this->objectStack[$h] . '}';

        $this->objectStack[$h] = ++$this->refCount;
        $line .= ('stdClass' !== $c ? ' ' : '') . '#' . $this->objectStack[$h];

        $h = null;
        $c = array($c => $c) + class_parents($a) + class_implements($a) + array('*' => '*');

        foreach ($c as $c)
        {
            if (isset($this->callbacks[$c = 'o:' . strtolower($c)]))
            {
                $c = $this->callbacks[$c];
                $h = false !== $c ? call_user_func($c, $a) : false;
                break;
            }
        }

        if (null === $h) $h = (array) $a;
        if (false === $h) $line .= '{...}';
        else $this->dumpArray($line, $h, '', '{', '}');
    }

    protected function dumpResource(&$line, $a)
    {
        $ref = substr((string) $a, 13);
        $type = get_resource_type($a);
        $line .= "Resource #{$ref} ({$type}";
        if (isset($this->resStack[$ref])) return $line .= ')';
        $this->resStack[$ref] = 1;

        if (isset($this->callbacks[$type = 'r:' . strtolower($type)]))
        {
            $type = call_user_func($this->callbacks[$type], $a);
            $this->dumpArray($line, $type, '', '[', '])');
        }
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

        if (method_exists($c, 'getClosureThis')) $a['this'] = $c->getClosureThis();

        if (false === $a['file'] = $c->getFileName()) unset($a['file']);
        else $a['lines'] = $c->getStartLine() . '-' . $c->getEndLine();

        if (!$c = $c->getStaticVariables()) unset($a['use']);
        else foreach ($c as $p => &$c) $a['use']['$' . $p] =& $c;

        return $a;
    }
}