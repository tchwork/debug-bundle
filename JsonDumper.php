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

class JsonDumper
{
    public

    $dumpHardRefs = true,
    $maxData   = 100000,
    $maxLength = 1000,
    $maxDepth  = 10;

    protected

    $tag,
    $token,
    $depth,
    $counter = 0,
    $recursiveArray = 0,
    $lines = array(),
    $refPool = array(),
    $valPool = array(),
    $arrPool = array(),
    $objPool = array(),
    $resPool = array(),
    $softPool = array(),
    $reserved = array('_' => 1, '__maxLength' => 1, '__maxDepth' => 1, '__proto__' => 1, '__refs' => 1),
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

    static function get($a)
    {
        $d = new self;
        $d->setCallback('line', array($d, 'pushLine'));
        $d->dumpLines($a);
        return implode("\n", $d->lines);
    }

    function dumpLines(&$a)
    {
        $this->token = ~mt_rand();
        $this->tag = array($this->token => (object) array());
        $this->counter = $this->depth = 0;

        $line = '';
        $this->dumpRef($line, $a);
        '' !== $line && call_user_func($this->callbacks['line'], $line, $this->depth);
        foreach ($this->arrPool as &$a) unset($a[$this->token]);
        $this->refPool = $this->valPool = $this->arrPool = $this->resPool = $this->objPool = array();
    }

    function setCallback($type, $callback)
    {
        $this->callbacks[strtolower($type)] = $callback;
    }

    protected function dumpRef(&$line, &$a)
    {
        ++$this->counter;

        if (is_array($a)) $this->dumpArray($line, $a);
        else
        {
            $v = $a;

            if ($this->dumpHardRefs && $this->depth)
            {
                $this->refPool[$this->counter] =& $a;
                $this->valPool[$this->counter] = $a;
                $a = $this->tag;
            }

            switch (true)
            {
            case null === $v: $line .= 'null'; break;
            case true === $v: $line .= 'true'; break;
            case false === $v: $line .= 'false'; break;
            case NAN === $v: $line .= '"f`NAN"'; break;
            case INF === $v: $line .= '"f`INF"'; break;
            case -INF === $v: $line .= '"f`-INF"'; break;

            case is_string($v): $this->dumpString($line, $v); break;
            case is_object($v): $this->dumpObject($line, $v); break;
            case is_resource($v): $this->dumpResource($line, $v); break;

            // float and integer
            default: $line .= (string) $v; break;
            }
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
        if (isset($a[$this->token]))
        {
            if ($a[$this->token] === $this->tag[$this->token]) $a[] = $this->counter;
            else $this->softPool[$this->counter] = $a[$this->token];
            return $line .= '"R`"';
        }

        $len = count($a);

        if ($this->dumpHardRefs) $r = 1;
        else
        {
/**/        if (PHP_VERSION_ID >= 50206)
/**/        {
                if (0 === $this->recursiveArray)
                {
                    // Detect recursive arrays by catching recursive count warnings
                    $this->recursiveArray = 1;
                    set_error_handler(array($this, 'catchRecursionWarning'));
                    count($a, COUNT_RECURSIVE);
                    restore_error_handler();
                }
                if (2 === $this->recursiveArray) $r = 1;
/**/        }
/**/        else
/**/        {
                $r = 1;
/**/        }
        }

        if (isset($r))
        {
            $a[$this->token] = $this->counter;
            $this->arrPool[] =& $a;
        }

        if ($len)
        {
            $line .= '{"_":"' . $this->counter . ':array:' . $len . '"';
            $this->dumpHash($line, $a, false);
        }
        else $line .= '[]';
    }

    protected function dumpObject(&$line, $a)
    {
        $h = spl_object_hash($a);

        if (isset($this->objPool[$h]))
        {
            $this->softPool[$this->counter] = $this->objPool[$h];
            return $line .= '"r`"';
        }
        else $this->objPool[$h] = $this->counter;

        $line .= '{"_":';
        $c = get_class($a);
        $this->dumpString($line, $this->counter . ':' . $c);

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
        if (false === $h) $line .= ', "__maxDepth": -1}';
        else
        {
            $c = $this->recursiveArray;
            $this->recursiveArray = 0;
            $this->dumpHash($line, $h, true);
            $this->recursiveArray = $c;
        }
    }

    protected function dumpResource(&$line, $a)
    {
        $h = (int) substr((string) $a, 13);

        if (isset($this->resPool[$h]))
        {
            $this->softPool[$this->counter] = $this->resPool[$h];
            return $line .= '"r`"';
        }
        else $this->resPool[$h] = $this->counter;

        $line .= '{"_":';
        $h = get_resource_type($a);
        $this->dumpString($line, $this->counter . ":resource:{$h}");

        if (isset($this->callbacks[$h = 'r:' . strtolower($h)]))
        {
            $h = call_user_func($this->callbacks[$h], $a);
            $this->dumpHash($line, $h, false);
        }
        else $line .= '}';
    }

    protected function dumpHash(&$line, &$a, $is_object)
    {
        if (!$a) return $line .= '}';

        $len = count($a);
        isset($a[$this->token]) && --$len;

        if ($this->depth === $this->maxDepth && 0 < $this->maxDepth)
            return $line .= ', "__maxDepth": ' . $len . '}';

        $i = 0;

        foreach ($a as $k => &$v)
        {
            if ($k === $this->token) continue;

            call_user_func($this->callbacks['line'], $line . ',', $this->depth);
            if (0 === $i) ++$this->depth;
            $line = '';

            if ($i === $this->maxLength && 0 < $this->maxLength) break;

            if ($is_object && isset($k[0]) && "\0" === $k[0]) $k = implode(':', explode("\0", substr($k, 1), 2));
            else if (isset($this->reserved[$k]) || false !== strpos($k, ':')) $k = ':' . $k;

            $this->dumpString($line, $k);
            $line .= ': ';

            $this->dumpRef($line, $v);
            ++$i;
        }

        if ($i && $len -= $i) $line .= '"__maxLength": ' . $len;

        if (1 === $this->depth)
        {
            $refs = array();

            foreach ($this->refPool as $k => &$v)
            {
                $len = $v;
                $v = $this->valPool[$k];
                isset($len[0]) && $refs[$k] = array_slice($len, 1);
            }

            foreach ($this->softPool as $len => $k) $refs[$k][] = $len;

            $line .= ', "__refs": {';
            foreach ($refs as $k => &$v) $v = '"' . $k . '":[' . implode(',', $v) . ']';
            $line .= implode(',', $refs) . '}';
        }

        call_user_func($this->callbacks['line'], $line, $this->depth);
        $i && --$this->depth;
        $line = '}';
    }

    function catchRecursionWarning()
    {
        $this->recursiveArray = 2;
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
