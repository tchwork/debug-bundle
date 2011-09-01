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

    $arrayDecycle = true,
    $maxData   = 100000,
    $maxLength = 1000,
    $maxDepth  = 10;

    protected

    $token,
    $depth,
    $refId,
    $lines = array(),
    $cycles = array(),
    $resStack = array(),
    $arrayStack = array(),
    $objectStack = array(),
    $reserved = array('_' => 1, '__maxLength' => 1, '__maxDepth' => 1, '__proto__' => 1, '__cyclicRefs' => 1),
    $callbacks = array(
        'line' => array(__CLASS__, 'echoLine'),
        'o:closure' => array(__CLASS__, 'castClosure'),
        'r:stream' => 'stream_get_meta_data',
        'r:process' => 'proc_get_status',
    );


    static function dump(&$a)
    {
        $d = new self;
        $d->dumpLines($a);
    }

    static function get(&$a)
    {
        $d = new self;
        $d->setCallback('line', array($d, 'pushLine'));
        $d->dumpLines($a);
        return implode("\n", $d->lines);
    }

    function dumpLines(&$a)
    {
        $this->token = "\x9D" . md5(mt_rand(), true);
        $this->refId = $this->depth = 0;

        $line = '';
        $this->refDump($line, $a);
        '' !== $line && call_user_func($this->callbacks['line'], $line, $this->depth);

        foreach ($this->arrayStack as &$a) unset($a[$this->token]);

        $this->cycles = $this->resStack = $this->arrayStack = $this->objectStack = array();
    }

    function setCallback($type, $callback)
    {
        $this->callbacks[strtolower($type)] = $callback;
    }

    protected function refDump(&$line, &$a)
    {
        switch (true)
        {
        case null === $a: $line .= 'null'; return;
        case true === $a: $line .= 'true'; return;
        case false === $a: $line .= 'false'; return;
        case NAN === $a: $line .= '"f`NAN"'; return;
        case INF === $a: $line .= '"f`INF"'; return;
        case -INF === $a: $line .= '"f`-INF"'; return;

        case is_array($a): $this->dumpArray($line, $a); return;
        case is_string($a): $this->dumpString($line, $a); return;
        case is_object($a): $this->dumpObject($line, $a); return;
        case is_resource($a): $this->dumpResource($line, $a); return;

        // float and integer
        default: $line .= (string) $a;
        }
    }

    protected function dumpString(&$line, $a)
    {
        if ('' === $a) return $line .= '""';

        if (!preg_match("''u", $a)) $a = 'b`' . utf8_encode($a);
        else if (false !== strpos($a, '`')) $a = 'u`' . $a;

        if (0 < $this->maxData && $this->maxData < $len = iconv_strlen($a, 'UTF-8') - 1)
            $a = $len . ('`' !== substr($a, 1, 1) ? 'u`' : '') . substr($a, 0, $this->maxData + 1);

        $line .= '"' . str_replace(
            array(
                  '\\', '"', '</',
                  "\x00",  "\x01",  "\x02",  "\x03",  "\x04",  "\x05",  "\x06",  "\x07",
                  "\x08",  "\x09",  "\x0A",  "\x0B",  "\x0C",  "\x0D",  "\x0E",  "\x0F",
                  "\x10",  "\x11",  "\x12",  "\x13",  "\x14",  "\x15",  "\x16",  "\x17",
                  "\x18",  "\x19",  "\x1A",  "\x1B",  "\x1C",  "\x1D",  "\x1E",  "\x1F",
            ),
            array(
                '\\\\', '\\"', '<\\/',
                '\u0000','\u0001','\u0002','\u0003','\u0004','\u0005','\u0006','\u0007',
                '\b'    ,'\t'    ,'\n'    ,'\u000B','\f'    ,'\r'    ,'\u000E','\u000F',
                '\u0010','\u0011','\u0012','\u0013','\u0014','\u0015','\u0016','\u0017',
                '\u0018','\u0019','\u001A','\u001B','\u001C','\u001D','\u001E','\u001F',
            ),
            $a
        ) . '"';
    }

    protected function dumpArray(&$line, &$a)
    {
        if (empty($a)) return $line .= '[]';

        if ($this->arrayDecycle)
        {
            if (empty($a[$this->token]))
            {
                $new = true;
                $a[$this->token] = ++$this->refId;
                $this->arrayStack[] =& $a;
            }
            else $this->cycles[$a[$this->token]] = 1;

            $line .= '{"_":"array:' . $a[$this->token] . ':len:' . (count($a) - 1);

            if (empty($new)) return $line .= ':"}';
        }
        else $line .= '{"_":"array::len:' . count($a);

        $this->dumpMap($line, $a, false);
    }

    protected function dumpObject(&$line, $a)
    {
        $h = spl_object_hash($a);
        $c = get_class($a);

        if (empty($this->objectStack[$h]))
        {
            $new = true;
            $this->objectStack[$h] = ++$this->refId;
        }
        else $this->cycles[$this->objectStack[$h]] = 1;

        $line .= '{"_":"' . str_replace('\\', '\\\\', $c) . ':' . $this->objectStack[$h];

        if (isset($new))
        {
            $h = null;
            $c = array($c => $c) + class_parents($a) + class_implements($a) + array('*' => '*');

            foreach ($c as $c)
            {
                if (isset($this->callbacks[$c = 'o:' . strtolower($c)]))
                {
                    $c = $this->callbacks[$c];
                    if (false !== $c) $h = call_user_func($c, $a);
                    else $h = false;
                    break;
                }
            }

            if (null === $h) $h = (array) $a;
            if (false === $h) $line .= '", "__maxDepth": -1}';
            else $this->dumpMap($line, $h, true);
        }
        else $line .= ':"}';
    }

    protected function dumpResource(&$line, $a)
    {
        $ref =& $this->resStack[(int) substr((string) $a, 13)];
        $type = get_resource_type($a);

        if (empty($ref))
        {
            $ref = ++$this->refId;
            $line .= "{\"_\":\"resource:{$type}:{$ref}";

            if (isset($this->callbacks[$type = 'r:' . strtolower($type)]))
            {
                $type = call_user_func($this->callbacks[$type], $a);
                $this->dumpMap($line, $type, false);
            }
            else $line .= '"}';
        }
        else
        {
            $this->cycles[$ref] = 1;
            $line .= "{\"_\":\"resource:{$type}:{$ref}:\"}";
        }
    }

    protected function dumpMap(&$line, array &$a, $is_object)
    {
        if (!$a) return $line .= '"}';

        $len = count($a);
        isset($a[$this->token]) && --$len;
        $line .= '"';

        if ($this->depth === $this->maxDepth && 0 < $this->maxDepth)
            return $line .= ', "__maxDepth": ' . $len . '}';

        ++$this->depth;
        $i = 0;

        foreach ($a as $k => &$v)
        {
            if ($this->token === $k) continue;

            call_user_func($this->callbacks['line'], $line . ',', $this->depth);
            $line = '';

            if ($i === $this->maxLength && 0 < $this->maxLength) break;

            if ($is_object && isset($k[0]) && "\0" === $k[0]) $k = implode(':', explode("\0", substr($k, 1), 2));
            else if (isset($this->reserved[$k]) || false !== strpos($k, ':')) $k = ':' . $k;

            $this->dumpString($line, $k);
            $line .= ': ';

            $this->refDump($line, $v);
            ++$i;
        }

        if ($len -= $i) $line .= '"__maxLength": ' . $len;
        if (0 === --$this->depth && $this->cycles) $line .= ', "__cyclicRefs": "#' . implode('#', array_keys($this->cycles)) . '#"';
        call_user_func($this->callbacks['line'], $line, $this->depth);
        $line = str_repeat('  ', $this->depth) . '}';
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

    static function echoLine($line, $depth)
    {
        echo str_repeat('  ', $depth), $line, "\n";
    }

    protected function pushLine($line, $depth)
    {
        $this->lines[] = str_repeat('  ', $depth) . $line;
    }
}
