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

    $maxData   = 1000,
    $maxLength = 100,
    $maxDepth  = 10;

    protected

    $lines,
    $token,
    $depth,
    $refId,
    $resStack = array(),
    $arrayStack = array(),
    $objectStack = array(),
    $callbacks = array(
        'line'      => array(__CLASS__, 'echoLine'),
        'o:closure' => array(__CLASS__, 'castClosure'),
        'r:stream'  => 'stream_get_meta_data',
    );


    static function dumpConst($a)
    {
        self::dump($a, false);
    }

    static function dump(&$a, $ref = true)
    {
        $d = new self;
        $d->dumpLines($a, $ref);
    }

    function dumpLines(&$a, $ref = true)
    {
        $this->token = "\x9D" . md5(mt_rand(), true);
        $this->refId = $this->depth = 0;

        $line = '';
        $this->refDump($line, $a, $ref ? '1' : '');
        '' !== $line && call_user_func($this->callbacks['line'], $line . "\n");

        foreach ($this->arrayStack as &$a) unset($a[$this->token]);

        $this->resStack = $this->arrayStack = $this->objectStack = array();
    }

    function setCallback($type, $callback)
    {
        $this->callbacks[strtolower($type)] = $callback;
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
        if (0 < $this->maxData && $this->maxData < strlen($a))
        {
            $tail = '"' . strlen($a);
            $a = substr($a, 0, $this->maxData - 3) . '...';
        }
        else $tail = '';

        if (false !== $j = strpos($a, "\n"))
        {
            $i = 0;
            $line .= '"""' . "\n";
            call_user_func($this->callbacks['line'], $line);

            $pre = str_repeat('  ', $this->depth+1);

            do
            {
                $line = $pre . addcslashes(substr($a, $i, $j - $i + 1), '\\"');
                call_user_func($this->callbacks['line'], $line);
                $i = $j + 1;
            }
            while (false !== $j = strpos($a, "\n", $i));

            $line = $pre . addcslashes(substr($a, $i), '\\"') . $tail . '"""';
        }
        else $line .= '"' . addcslashes($a, '\\"') . $tail . '"';
    }

    protected function dumpArray(&$line, &$a, $ref, $open = '[', $close = ']')
    {
        if (!$a) return $line .= $open . $close;

        if ($ref)
        {
            if (isset($a[$this->token])) return $line .= "{$open}#{$a[$this->token]}{$close}";
            $a[$this->token] = ++$this->refId;
            $ref = '#' . $a[$this->token];
            $this->arrayStack[] =& $a;
        }

        $line .= $ref . $open;

        if ($this->depth === $this->maxDepth && 0 < $this->maxDepth)
        {
            $line .= '...' . $close;
            return;
        }

        ++$this->depth;
        $i = 0;
        $pre = str_repeat('  ', $this->depth);

        foreach ($a as $k => &$v)
        {
            if ($this->token === $k) continue;

            call_user_func($this->callbacks['line'], $line . "\n");
            $line = $pre;

            if ($i === $this->maxLength && 0 < $this->maxLength)
            {
                $line .= '..."' . (count($a) - 1);
                break;
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
            ++$i;
        }

        call_user_func($this->callbacks['line'], $line . "\n");
        $line = substr($pre, 0, -2) . $close;
        --$this->depth;
    }

    protected function dumpObject(&$line, $a)
    {
        $h = spl_object_hash($a);
        $c = get_class($a);
        $line .= 'stdClass' !== $c ? $c : '';

        if (isset($this->objectStack[$h])) return $line .= '{#' . $this->objectStack[$h] . '}';

        $this->objectStack[$h] = ++$this->refId;
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

    static function echoLine($line)
    {
        echo $line;
    }
}
