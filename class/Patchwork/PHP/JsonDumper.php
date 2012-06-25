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
 * JsonDumper implements the JSON convention to dump any PHP variable with high accuracy.
 *
 * See https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Dumping-PHP-Data-en.md
 */
class JsonDumper extends Dumper
{
    public

    $maxString = 100000;

    protected

    $line = '',
    $lastHash = 0;

    protected static

    $lines = array();

    static function dump(&$a)
    {
        $d = new self;
        $d->setCallback('line', array(__CLASS__, 'echoLine'));
        $d->walk($a);
    }

    static function get($a)
    {
        $d = new self;
        $d->setCallback('line', array(__CLASS__, 'stackLine'));
        $d->walk($a);
        $d = implode("\n", self::$lines);
        self::$lines = array();
        return $d;
    }


    function walk(&$a)
    {
        $this->line = '';
        $this->lastHash = 0;
        parent::walk($a);
        '' !== $this->line && $this->dumpLine(0);
    }

    protected function dumpLine($depth_offset)
    {
        call_user_func($this->callbacks['line'], $this->line, $this->depth + $depth_offset);
        $this->line = '';
    }

    protected function dumpRef($is_soft, $ref_counter = null, &$ref_value = null)
    {
        if (parent::dumpRef($is_soft, $ref_counter, $ref_value)) return;

        $is_soft = $is_soft ? 'r' : 'R';
        $this->line .= "\"{$is_soft}`{$this->counter}:{$ref_counter}\"";
    }

    protected function dumpScalar($a)
    {
        switch (true)
        {
        case null === $a: $this->line .= 'null'; break;
        case true === $a: $this->line .= 'true'; break;
        case false === $a: $this->line .= 'false'; break;
        case INF === $a: $this->line .= '"n`INF"'; break;
        case -INF === $a: $this->line .= '"n`-INF"'; break;
        case is_nan($a): $this->line .= '"n`NAN"'; break;
        case $a > 9007199254740992 && is_int($a): $a = '"n`' . $a . '"'; // JavaScript max integer is 2^53
        default: $this->line .= (string) $a; break;
        }
    }

    protected function dumpString($a, $is_key)
    {
        if ($is_key)
        {
            $is_key = $this->lastHash === $this->counter && !isset($this->depthLimited[$this->counter]);
            $this->dumpLine(-$is_key, $this->line .= ',');
            $is_key = ': ';
        }
        else $is_key = '';

        if ('' === $a) return $this->line .= '""' . $is_key;

        if (!preg_match("''u", $a)) $a = 'b`' . utf8_encode($a);
        else if (false !== strpos($a, '`')) $a = 'u`' . $a;

        if (0 < $this->maxString && $this->maxString < $len = iconv_strlen($a, 'UTF-8') - 1)
            $a = $len . ('`' !== substr($a, 1, 1) ? 'u`' : '') . substr($a, 0, $this->maxString + 1);

        static $map = array(
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
        );

        $this->line .= '"' . str_replace($map[0], $map[1], $a) . '"' . $is_key;
    }

    protected function walkHash($type, &$a)
    {
        if ('array:0' === $type) $this->line .= '[]';
        else
        {
            $h = $this->lastHash;
            $this->line .= '{"_":';
            $this->lastHash = $this->counter;
            $this->dumpString($this->counter . ':' . $type, false);

            if ($type = parent::walkHash($type, $a))
            {
                ++$this->depth;
                $this->dumpString('__refs', true);
                $this->line .= '{';
                foreach ($type as $k => &$a) $a = '"' . $k . '":[' . implode(',', $a) . ']';
                $this->line .= implode(',', $type) . '}';
                --$this->depth;
            }

            if ($this->counter !== $this->lastHash || isset($this->depthLimited[$this->counter]))
                $this->dumpLine(1);

            $this->lastHash = $h;
            $this->line .= '}';
        }
    }


    protected static function echoLine($line, $depth)
    {
        echo str_repeat('  ', $depth), $line, "\n";
    }

    protected static function stackLine($line, $depth)
    {
        self::$lines[] = str_repeat('  ', $depth) . $line;
    }
}
