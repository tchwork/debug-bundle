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
 * CliDumper dumps variable for command line output.
 */
class CliDumper extends DepthFirstDumper
{
    public

    $colors = null,
    $maxString = 10000,
    $maxStringWidth = 120;

    public static

    $defaultColors = null,
    $defaultOutputStream = 'php://stderr';

    protected

    $styles = array(
        // See http://en.wikipedia.org/wiki/ANSI_escape_code#graphics
        'num'       => '1;38;5;33',
        'const'     => '1;38;5;33',
        'str'       => '1;38;5;45',
        'cchr'      => '7',
        'note'      => '38;5;178',
        'ref'       => '38;5;238',
        'public'    => '38;5;28',
        'protected' => '38;5;166',
        'private'   => '38;5;160',
        'meta'      => '38;5;27',
    );


    public function __construct($outputStream = null, array $defaultCasters = null)
    {
        parent::__construct($outputStream, $defaultCasters);

        if (! isset($this->colors) && ! isset($outputStream))
        {
            isset(static::$defaultColors) or static::$defaultColors = $this->supportsColors();
            $this->colors = static::$defaultColors;
        }
    }

    public function setStyles(array $styles)
    {
        $this->styles = $styles + $this->styles;
    }

    protected function dumpRef($isSoft, $position, $hash)
    {
        if (parent::dumpRef($isSoft, $position, $hash)) return true;

        if (! $position || $position === $this->position)
        {
            $this->line .= $this->style('ref', ($isSoft ? '@' : '#') . $this->position);
        }
        else
        {
            if (null === $hash) $note = '';
            else if (isset($hash[0])) $note = get_class($this->valPool[$position]) . ' ';
            else if ($hash) $note = 'resource:' . get_resource_type($this->valPool[$position]) . ' ';
            else $note = 'array ';

            $this->line .= $this->style('note', $note . ($isSoft ? '@' : '&') . $position);
        }

        return false;
    }

    protected function dumpScalar($val)
    {
        if (is_int($val))
        {
            $s = 'num';
            $v = $val;
        }
        else if (is_float($val))
        {
            $s = 'num';

            switch (true)
            {
            case INF === $val:  $v = 'INF';  break;
            case -INF === $val: $v = '-INF'; break;
            case is_nan($val):  $v = 'NAN';  break;
            default:
                $v = sprintf('%.14E', $val);
                $val = sprintf('%.17E', $val);
                $v = preg_replace('/(\d)0*(?:E\+0|(E)\+?(.*))$/', '$1$2$3', (float) $v === (float) $val ? $v : $val);
                break;
            }
        }
        else
        {
            $s = 'const';

            switch (true)
            {
            case null === $val:  $v = 'null';  break;
            case true === $val:  $v = 'true';  break;
            case false === $val: $v = 'false'; break;
            default: $v = (string) $val; break;
            }
        }

        $this->line .= $this->style($s, $v);
    }

    protected function dumpString($str, $isKey, $style = null)
    {
        if ($isKey)
        {
            $isKey = $this->hashPosition === $this->position;

            if ('__cutBy' === $str)
            {
                if (! $isKey) $this->dumpLine(0);
                else $this->line .= ' ';
                $this->line .= '…';

                return;
            }

            $isKey = $isKey && ! isset($this->depthLimited[$this->position]);
            $this->dumpLine(-$isKey);
            $isKey = ': ';

            if (is_int($str))
            {
                $this->line .= $this->style('num', $str) . $isKey;

                return;
            }

            $str = explode(':', $str, 2);

            if (isset($str[1]))
            {
                if (! isset($style))
                {
                    switch ($str[0])
                    {
                    case '':  $style = 'public';    break;
                    case '*': $style = 'protected'; break;
                    case '~': $style = 'meta';      break;
                    default:  $style = 'private';   break;
                    }
                }

                $str = $str[1];
            }
            else
            {
                $str = $str[0];
                isset($style) or $style = 'public';
            }
        }
        else $isKey = '';

        if ('' === $str) {
            $this->line .= "''" . $isKey;

            return;
        }

        isset($style) or $style = 'str';

        if ($bin = ! preg_match('//u', $str))
        {
            $str = utf8_encode($str);
        }

        if (0 < $this->maxString && $this->maxString < $len = iconv_strlen($str, 'UTF-8'))
        {
            $str = iconv_substr($str, 0, $this->maxString - 1, 'UTF-8');
            $cutBy = $len - $this->maxString + 1;
        }
        else $cutBy = 0;

        if ($this->maxLength > 0)
        {
            $str = explode("\n", $str, $this->maxLength + 1);
            if (isset($str[$this->maxLength]))
            {
                $cutBy += iconv_strlen($str[$this->maxLength], 'UTF-8');
                $str[$this->maxLength] = '';
            }
        }
        else $str = explode("\n", $str);

        $x = isset($str[1]);
        $i = $len = 0;

        foreach ($str as $str)
        {
            if ($isKey ? $i++ : $x)
            {
                $this->dumpLine(0);
                $isKey or $this->line .= '  ';
            }

            $len = iconv_strlen($str, 'UTF-8');

            if (0 < $this->maxStringWidth && $this->maxStringWidth < $len)
            {
                $str = iconv_substr($str, 0, $this->maxStringWidth - 1, 'UTF-8');
                $str = $this->style($style, $str) . '…';
                $cutBy += $len - $this->maxStringWidth + 1;
            }
            else
            {
                $str = $this->style($style, $str);
            }

            if ($bin)
            {
                if (' ' === substr($this->line, -1))
                {
                    $this->line = substr_replace($this->line, 'b' . $str, -1);
                }
                else
                {
                    $this->line .= 'b' . $str;
                }
            }
            else $this->line .= $str;
        }

        if ($cutBy)
        {
            if (0 >= $this->maxStringWidth || $this->maxStringWidth >= $len)
            {
                $this->line .= '…';
            }

            $this->dumpScalar($cutBy);
        }

        $this->line .= $isKey;
    }

    protected function dumpHash($type, $array)
    {
        $isArray = 'array' === $type;

        if (empty($array) && $isArray) $this->line .= '[]';
        else
        {
            if ($isArray)
            {
                $this->line .= '[';
                //$this->dumpString(count($array), false, 'note');
            }
            else
            {
                $this->dumpString($type, false, 'note');
                $this->line .= '{';
            }

            $this->line .= ' ' . $this->style('ref', "#$this->position");

            $startPosition = $this->position;
            $refs = parent::dumpHash($type, $array);
            if ($this->position !== $startPosition) $this->dumpLine(1);

            $this->line .= $isArray ? ']' : '}';

            if ($refs)
            {
                $col1 = 0;
                $type = array();

                foreach ($refs as $k => $v)
                {
                    if (isset($this->valPool[$k]))
                    {
                        $v = $this->valPool[$k];
                        $type[$k] = gettype($v);

                        switch ($type[$k])
                        {
                        case 'object': $type[$k] = get_class($v); break;
                        case 'unknown type':
                        case 'resource': $type[$k] = 'resource:' . get_resource_type($v); break;
                        }

                        $col1 = max($col1, strlen($type[$k]));
                    }
                }

                $col2 = strlen($this->position);

                foreach ($refs as $k => $v)
                {
                    $this->dumpLine(0);

                    $this->line .= str_repeat(' ', $col2 - strlen($k));
                    $this->line .= $this->style('ref', "#$k");

                    if ($col1)
                    {
                        $this->line .= sprintf(" % -{$col1}s", isset($type[$k]) ? $type[$k] : 'array');
                    }

                    $this->line .= ':';

                    foreach ($v as $v)
                    {
                        $this->line .= ' ' . $this->style('note', $v < 0 ? '&' . -$v : "@$v");
                    }
                }
            }
        }
    }

    protected function style($style, $val)
    {
        isset($this->colors) or $this->colors = $this->supportsColors();

        if (! $this->colors) return $val;

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
                if (false !== strpos($val, $c))
                {
                    $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                    $r = "\e[{$this->styles[$style]};{$this->styles['cchr']}m{$r}\e[m";
                    $r = "\e[m{$r}\e[{$this->styles[$style]}m";
                    $val = str_replace($c, $r, $val);
                }
            }
        }

        return sprintf("\e[%sm%s\e[m", $this->styles[$style], $val);
    }

    protected function supportsColors()
    {
        if (isset($_SERVER['argv'][1]))
        {
            $colors = $_SERVER['argv'];
            $i = count($colors);
            while (--$i > 0)
            {
                if (isset($colors[$i][5]))
                switch ($colors[$i])
                {
                case '--ansi':
                case '--color':
                case '--color=yes':
                case '--color=force':
                case '--color=always':
                    return true;

                case '--no-ansi':
                case '--color=no':
                case '--color=none':
                case '--color=never':
                    return false;
                }
            }
        }

        if (null !== static::$defaultColors) return static::$defaultColors;

        if (empty($this->outputStream)) return false;

        $this->lastErrorMessage = true;

        $colors = DIRECTORY_SEPARATOR === '\\'
            ? (false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI'))
            : (function_exists('posix_isatty') && posix_isatty($this->outputStream));

        $this->lastErrorMessage = false;

        return $colors;
    }
}
