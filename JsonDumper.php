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

class JsonDumper extends Dumper
{
    public

    $maxString = 100000;

    protected

    $line = '',
    $lines = array(),
    $hashCounter;


    static function dump(&$a)
    {
        $d = new self;
        $d->setCallback('line', array($d, 'echoLine'));
        $d->walk($a);
    }

    static function get($a)
    {
        $d = new self;
        $d->setCallback('line', array($d, 'stackLine'));
        $d->walk($a);
        return implode("\n", $d->lines);
    }


    function walk(&$a)
    {
        $this->line = '';
        parent::walk($a);
        '' !== $this->line && $this->dumpLine(0);
    }

    protected function dumpLine($depth_offset)
    {
        call_user_func($this->callbacks['line'], $this->line, $this->depth + $depth_offset);
        $this->line = '';
    }

    protected function dumpRef($is_soft)
    {
        $this->line .= $is_soft ? '"r`"' : '"R`"';
    }

    protected function dumpScalar($a)
    {
        switch (true)
        {
        case null === $a: $this->line .= 'null'; break;
        case true === $a: $this->line .= 'true'; break;
        case false === $a: $this->line .= 'false'; break;
        case INF === $a: $this->line .= '"f`INF"'; break;
        case -INF === $a: $this->line .= '"f`-INF"'; break;
        case is_nan($a): $this->line .= '"f`NAN"'; break;
        default: $this->line .= (string) $a; break;
        }
    }

    protected function dumpString($a, $is_key = '')
    {
        if ($is_key) $this->dumpLine(-($this->hashCounter === $this->counter), $this->line .= ',');

        if ('' === $a) return $this->line .= '""' . $is_key;

        if (!preg_match("''u", $a)) $a = 'b`' . utf8_encode($a);
        else if (false !== strpos($a, '`')) $a = 'u`' . $a;

        if (0 < $this->maxString && $this->maxString < $len = iconv_strlen($a, 'UTF-8') - 1)
            $a = $len . ('`' !== substr($a, 1, 1) ? 'u`' : '') . substr($a, 0, $this->maxString + 1);

        $this->line .= '"' . str_replace(
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
        ) . '"' . $is_key;
    }

    protected function walkHash($type, &$a)
    {
        if ('array:0' === $type) $this->line .= '[]';
        else
        {
            $this->line .= '{"_":';
            $this->hashCounter = $this->counter;
            $this->dumpString($this->counter . ':' . $type);

            if ($type = parent::walkHash($type, $a))
            {
                $this->line .= ', "__refs": {';
                foreach ($type as $k => &$a) $a = '"' . $k . '":[' . implode(',', $a) . ']';
                $this->line .= implode(',', $type) . '}';
            }

            if ($this->counter !== $this->hashCounter) $this->dumpLine(1);

            $this->line .= '}';
        }
    }


    static function echoLine($line, $depth)
    {
        echo str_repeat('  ', $depth), $line, "\n";
    }

    protected function stackLine($line, $depth)
    {
        $this->lines[] = str_repeat('  ', $depth) . $line;
    }
}
