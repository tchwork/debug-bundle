<?php

namespace Patchwork\Dumper;

/**
 * CliDumper dumps variable for command line output.
 */
class CliDumper extends AbstractDumper implements DumperInterface
{
    public static $defaultColors = null;
    public static $defaultOutputStream = 'php://stderr';

    public $colors = null;
    public $maxStringWidth = 0;

    protected $styles = array(
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


    public function __construct($outputStream = null)
    {
        parent::__construct($outputStream);

        if (!isset($this->colors) && !isset($outputStream)) {
            isset(static::$defaultColors) or static::$defaultColors = $this->supportsColors();
            $this->colors = static::$defaultColors;
        }
    }

    public function setStyles(array $styles)
    {
        $this->styles = $styles + $this->styles;
    }

    public function dumpScalar(Cursor $cursor, $type, $val)
    {
        if ('string' === $type) {
            return $this->dumpString($cursor, $val, false, 0);
        }

        $this->dumpKey($cursor);

        if (false !== $cursor->refTo) {
            $this->line .= $this->style('note', ($cursor->refIsHard ? '&' : '@').$cursor->refTo);
        }

        $style = 'const';

        switch ($type)
        {
            case 'int':
                $style = 'num';
                break;

            case 'float':
                $style = 'num';

                switch (true) {
                    case INF === $val:  $val = 'INF';  break;
                    case -INF === $val: $val = '-INF'; break;
                    case is_nan($val):  $val = 'NAN';  break;
                    default:
                        $v = sprintf('%.14E', $val);
                        $val = sprintf('%.17E', $val);
                        $val = preg_replace('/(\d)0*(?:E\+0|(E)\+?(.*))$/', '$1$2$3', (float) $v === (float) $val ? $v : $val);
                        break;
                }
                break;

            case 'NULL':
                $val = 'null';
                break;

            case 'boolean':
                $val = $val ? 'true' : 'false';
                break;
        }

        $this->line .= $this->style($style, $val);
        $this->endLine($cursor);
    }

    public function dumpString(Cursor $cursor, $str, $bin, $cut)
    {
        $this->dumpKey($cursor);

        if (false !== $cursor->refTo) {
            $this->line .= $this->style('note', ($cursor->refIsHard ? '&' : '@').$cursor->refTo);
        }

        if ('' === $str) {
            $this->line .= "''";
            $this->endLine($cursor);
        } else {
            $str = explode("\n", $str);
            $m = count($str) - 1;
            $i = 0;

            if ($m) {
                $this->endLine($cursor);
            }

            foreach ($str as $str) {

                if (0 < $this->maxStringWidth && $this->maxStringWidth < $len = iconv_strlen($str, 'UTF-8')) {
                    $str = iconv_substr($str, 0, $this->maxStringWidth - 1, 'UTF-8');
                    $str = $this->style('str', $str).'…';
                } else {
                    $str = $this->style('str', $str);
                }

                if ($bin) {
                    if ($m) {
                        $this->line .= ' b'.$str;
                    } elseif (' ' === substr($this->line, -1)) {
                        $this->line = substr_replace($this->line, 'b'.$str, -1);
                    } else {
                        $this->line .= 'b'.$str;
                    }
                } elseif ($m) {
                    $this->line .= '  '.$str;
                } else {
                    $this->line .= $str;
                }

                if ($i == $m) {
                    if ($cut) {
                        if (0 >= $this->maxStringWidth || $this->maxStringWidth >= $len) {
                            $this->line .= '…';
                        }
                        $this->line .= $cut;
                    }
                }

                $this->endLine($cursor, !$m);
            }
        }
    }

    public function enterArray(Cursor $cursor, $count, $cut, $indexed)
    {
        $this->enterHash($cursor, '[');
    }

    public function leaveArray(Cursor $cursor, $count, $cut, $indexed)
    {
        $this->leaveHash($cursor, $cut, ']');
    }

    public function enterObject(Cursor $cursor, $class, $cut)
    {
        $this->enterHash($cursor, $this->style('note', $class).'{');
    }

    public function leaveObject(Cursor $cursor, $class, $cut)
    {
        $this->leaveHash($cursor, $cut, '}');
    }

    public function enterResource(Cursor $cursor, $res, $cut)
    {
        $this->enterHash($cursor, 'resource:'.$this->style('note', $res).'{');
    }

    public function leaveResource(Cursor $cursor, $res, $cut)
    {
        $this->leaveHash($cursor, $cut, '}');
    }

    protected function enterHash(Cursor $cursor, $prefix)
    {
        $this->dumpKey($cursor);

        $this->line .= $prefix;
        if (false !== $cursor->refTo) {
            $this->line .= $this->style('ref', ($cursor->refIsHard ? '&' : '@').$cursor->refTo);
        } elseif ($cursor->dumpedChildren) {
            $this->endLine($cursor);
        }
    }

    protected function leaveHash(Cursor $cursor, $cut, $suffix)
    {
        if ($cut) {
            $this->line .= '…';
            if (0 < $cut) {
                $this->line .= $cut;
            }
        }
        $this->line .= $suffix;
        $this->endLine($cursor, !$cursor->dumpedChildren);
    }

    protected function dumpKey(Cursor $cursor)
    {
        if (null !== $key = $cursor->hashKey) {
            switch ($cursor->hashType) {
                case $cursor::HASH_INDEXED:
                    return;

                case $cursor::HASH_ASSOC:
                    $style = 'meta';
                    break;

                default:
                case $cursor::HASH_OBJECT:
                case $cursor::HASH_RESOURCE:
                    if (!isset($key[0]) || "\0" !== $key[0]) {
                        $style = 'public';
                    } else {
                        $key = explode("\0", substr($key, 1), 3);

                        switch ($key[0]) {
                            case '~': $style = 'meta';      break;
                            case '*': $style = 'protected'; break;
                            default:  $style = 'private';   break;
                        }

                        $key = $key[1];
                    }
                    break;
            }

            $this->line .= $this->style($style, $key).': ';
        }
    }

    protected function endLine(Cursor $cursor, $showRef = true)
    {
        if ($showRef && false !== $cursor->refIndex) {
            $this->line .= ' '.$this->style('ref', '#'.$cursor->refIndex);
        }
        $this->dumpLine($cursor->depth);
    }

    protected function style($style, $val)
    {
        isset($this->colors) or $this->colors = $this->supportsColors();

        if (!$this->colors) {
            return $val;
        }

        if ('str' === $style || 'public' === $style) {
            static $cchr = array(
                "\x1B", // ESC must be the first
                "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
                "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
                "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
                "\x18", "\x19", "\x1A", "\x1C", "\x1D", "\x1E", "\x1F", "\x7F",
            );

            foreach ($cchr as $c) {
                if (false !== strpos($val, $c)) {
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
        if (isset($_SERVER['argv'][1])) {
            $colors = $_SERVER['argv'];
            $i = count($colors);
            while (--$i > 0) {
                if (isset($colors[$i][5])) {
                    switch ($colors[$i]) {
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
        }

        if (null !== static::$defaultColors) {
            return static::$defaultColors;
        }

        if (empty($this->outputStream)) {
            return false;
        }

        $colors = DIRECTORY_SEPARATOR === '\\'
            ? @(false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI'))
            : @(function_exists('posix_isatty') && posix_isatty($this->outputStream));

        return $colors;
    }
}
