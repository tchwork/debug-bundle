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
 * CliColorDumper dumps variable for command line output.
 */
class CliColorDumper extends Dumper
{
    public

    $maxStringWidth = 80;

    protected

    $line = '',
    $lastHash = 0,
    $styles = array(
        // See http://en.wikipedia.org/wiki/ANSI_escape_code#graphics
        'num'       => '38;5;4',
        'const'     => '38;5;4',
        'str'       => '38;5;9',
        'cchr'      => '7',
        'note'      => '38;5;11',
        'ref'       => '38;5;8',
        'public'    => '38;5;2',
        'protected' => '38;5;3',
        'private'   => '38;5;1',
        'meta'      => '38;5;4',
    );

    static function dump(&$a)
    {
        $d = new self;
        $d->setCallback('line', array(__CLASS__, 'echoLine'));
        $d->walk($a);
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

    protected function dumpRef($is_soft, $ref_counter = null, &$ref_value = null, $ref_type = null)
    {
        if (parent::dumpRef($is_soft, $ref_counter, $ref_value, $ref_type)) return true;

        $is_soft = $is_soft ? '@' : '#';

        if (! $ref_counter)
        {
            $this->line .= $this->style('ref', $is_soft . $this->counter);
        }
        else
        {
            $note = $is_soft . $ref_counter;

            if (isset($ref_type)) switch ($ref_type)
            {
            case 'array': $note = 'array ' . $note; break;
            case 'object': $note = get_class($ref_value) . ' ' . $note; break;
            case 'resource': $note = 'resource:' . get_resource_type($ref_value) . ' ' . $note; break;
            case 'string': $ths->dumpString($ref_value, false); break;
            default: $ths->dumpScalar($ref_value); break;
            }

            $this->line .= $this->style('note', $note);
        }

        return false;
    }

    protected function dumpScalar($a)
    {
        if (is_int($a))
        {
            $s = 'num';
            $b = $a;
        }
        else if (is_float($a))
        {
            $s = 'num';

            switch (true)
            {
            case INF === $a:   $b = 'INF';   break;
            case -INF === $a:  $b = '-INF';  break;
            case is_nan($a):   $b = 'NAN';   break;
            case is_float($a):
                $b = sprintf('%.14E', $a);
                $a = sprintf('%.17E', $a);
                $b = preg_replace('/(\d)0*(?:E\+0|(E)\+?(.*))$/', '$1$2$3', (float) $b === (float) $a ? $b : $a);
                break;
            }
        }
        else
        {
            $s = 'const';

            switch (true)
            {
            case null === $a:  $b = 'null';  break;
            case true === $a:  $b = 'true';  break;
            case false === $a: $b = 'false'; break;
            default: $b = (string) $a; break;
            }
        }

        $this->line .= $this->style($s, $b);
    }

    protected function dumpString($a, $is_key, $style = null)
    {
        if ($is_key)
        {
            $is_key = $this->lastHash === $this->counter;

            if ('__cutBy' === $a)
            {
                if (! $is_key) $this->dumpLine(0);
                else $this->line .= ' ';
                $this->line .= '…';
                return;
            }

            $is_key = $is_key && ! isset($this->depthLimited[$this->counter]);
            $this->dumpLine(-$is_key);
            $is_key = ': ';

            $a = explode(':', $a);

            if (isset($a[1]))
            {
                if (! isset($style))
                {
                    switch ($a[0])
                    {
                    case '':  $style = 'public';    break;
                    case '*': $style = 'protected'; break;
                    case '~': $style = 'meta';      break;
                    default:  $style = 'private';   break;
                    }
                }

                $a = $a[1];
            }
            else
            {
                $a = $a[0];
                isset($style) or $style = 'public';
            }
        }
        else $is_key = '';

        if ('' === $a) return $this->line .= '"' . $is_key;

        isset($style) or $style = 'str';

        if ($bin = ! preg_match('//u', $a))
        {
            $a = utf8_encode($a);
        }

        $a = explode("\n", $a);
        $x = isset($a[1]);
        $i = 0;

        foreach ($a as $a)
        {
            if ($is_key ? $i++ : $x)
            {
                $this->dumpLine(0);
                $is_key or $this->line .= '  ';
            }

            $len = iconv_strlen($a);

            if (0 < $this->maxStringWidth && $this->maxStringWidth < $len)
            {
                $a = iconv_substr($a, 0, $this->maxStringWidth - 1, 'UTF-8');
                $a = $this->style($style, $a) . '…';
            }
            else
            {
                $a = $this->style($style, $a);
            }

            if ($bin)
            {
                if (' ' === substr($this->line, -1))
                {
                    $this->line = substr_replace($this->line, 'b' . $a, -1);
                }
                else
                {
                    $this->line .= 'b' . $a;
                }
            }
            else $this->line .= $a;
        }

        $this->line .= $is_key;
    }

    protected function walkHash($type, &$a, $len)
    {
        if ('array:0' === $type) $this->line .= '[]';
        else
        {
            $h = $this->lastHash;
            $this->lastHash = $this->counter;

            $is_array = 0 === strncmp($type, 'array:', 6);

            if ($is_array)
            {
                $this->line .= '[';
                //$this->dumpString(substr($type, 6), false, 'note');
            }
            else
            {
                $this->dumpString($type, false, 'note');
                $this->line .= '{';
            }

            $this->line .= ' ' . $this->style('ref', "#$this->counter");

            $type = parent::walkHash($type, $a, $len);

            if ($this->counter !== $this->lastHash) $this->dumpLine(1);

            $this->lastHash = $h;
            $this->line .= $is_array ? ']' : '}';

            if ($type)
            {
                $fmt = strlen($this->counter);

                foreach ($type as $k => $v)
                {
                    $this->dumpLine(0);

                    $this->line .= str_repeat(' ', $fmt - strlen($k));
                    $this->line .= $this->style('ref', "#$k");
                    $this->line .= ':';

                    foreach ($v as $v)
                    {
                        $this->line .= ' ' . $this->style('note', $v < 0 ? '#' . -$v : "@$v");
                    }
                }
            }
        }
    }

    protected function style($style, $a)
    {
        switch ($style)
        {
        case 'str':
        case 'public':
            static $cchr = array(
                "\x1B", // ESC must be the first
                "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
                "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
                "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
                "\x18", "\x19", "\x1A", "\x1C", "\x1D", "\x1E", "\x1F", "\x7F",
            );

            foreach ($cchr as $c)
            {
                if (false !== strpos($a, $c))
                {
                    $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                    $r = "\e[{$this->styles[$style]};{$this->styles['cchr']}m{$r}\e[m";
                    $r = "\e[m{$r}\e[{$this->styles[$style]}m";
                    $a = str_replace($c, $r, $a);
                }
            }
        }

        return sprintf("\e[%sm%s\e[m", $this->styles[$style], $a);
    }


    protected static function echoLine($line, $depth)
    {
        echo str_repeat('  ', $depth), $line, "\n";
    }
}
