<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper;

/**
 * JsonDumper implements the JSON convention to dump any PHP variable with high accuracy.
 *
 * See https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Dumping-PHP-Data-en.md
 */
class JsonDumper extends Dumper
{
    public

    $maxString = 100000;


    protected function dumpRef($isSoft, $position, $hash)
    {
        if (parent::dumpRef($isSoft, $position, $hash)) return true;

        $isSoft = $isSoft ? 'r' : 'R';
        $this->line .= "\"{$isSoft}`{$this->position}:{$position}\"";

        return false;
    }

    protected function dumpScalar($val)
    {
        switch (true)
        {
        case null === $val: $this->line .= 'null'; break;
        case true === $val: $this->line .= 'true'; break;
        case false === $val: $this->line .= 'false'; break;
        case INF === $val: $this->line .= '"n`INF"'; break;
        case -INF === $val: $this->line .= '"n`-INF"'; break;
        case is_nan($val): $this->line .= '"n`NAN"'; break;
        case $val > 9007199254740992 && is_int($val): $val = '"n`' . $val . '"'; // JavaScript max integer is 2^53
        default: $this->line .= (string) $val; break;
        }
    }

    protected function dumpString($str, $isKey)
    {
        if ($isKey)
        {
            $this->line .= ',';
            $isKey = $this->hashPosition === $this->position;

            if ('__cutBy' === $str)
            {
                if (! $isKey) $this->dumpLine(0);
            }
            else
            {
                $isKey = $isKey && ! isset($this->depthLimited[$this->position]);
                $this->dumpLine(-$isKey);
            }

            $isKey = ': ';
        }
        else $isKey = '';

        if ('' === $str) return $this->line .= '""' . $isKey;

        if (! preg_match('//u', $str)) $str = 'b`' . utf8_encode($str);
        else if (false !== strpos($str, '`')) $str = 'u`' . $str;

        if (0 < $this->maxString && $this->maxString < $len = iconv_strlen($str, 'UTF-8') - 1)
            $str = $len . ('`' !== substr($str, 1, 1) ? 'u`' : '') . iconv_substr($str, 0, $this->maxString + 1, 'UTF-8');

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

        $this->line .= '"' . str_replace($map[0], $map[1], $str) . '"' . $isKey;
    }

    protected function dumpHash($type, $array)
    {
        if ('array' === $type) $type .= ':' . count($array);

        if ('array:0' === $type) $this->line .= '[]';
        else
        {
            $this->line .= '{"_":';
            $this->dumpString($this->position . ':' . $type, false);

            $startPosition = $this->position;

            if ($type = parent::dumpHash($type, $array))
            {
                ++$this->depth;
                $this->dumpString('__refs', true);
                foreach ($type as $k => $v) $type[$k] = '"' . $k . '":[' . implode(',', $v) . ']';
                $this->line .= '{' . implode(',', $type) . '}';
                --$this->depth;
            }

            if ($this->position !== $startPosition) $this->dumpLine(1);

            $this->line .= '}';
        }
    }
}
