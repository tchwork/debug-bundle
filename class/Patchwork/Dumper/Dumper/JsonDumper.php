<?php

namespace Patchwork\Dumper\Dumper;

use Patchwork\Dumper\Collector\Data;

/**
 * JsonDumper implements the JSON convention to dump any PHP variable with high accuracy.
 */
class JsonDumper extends AbstractDumper implements DumperInterface
{
    protected static $reserved = array(
        '_' => 1,
        '__cutBy' => 1,
        '__refs' => 1,
        '__proto__' => 1,
    );

    protected $position = 0;
    protected $refsPos = array();
    protected $refs = array();

    public function dumpScalar(Cursor $cursor, $type, $val)
    {
        if ('string' === $type) {
            return $this->dumpString($cursor, $val, false, 0);
        }

        if ($this->dumpKey($cursor)) {
            return;
        }

        switch (true) {
            case null === $val: $this->line .= 'null'; break;
            case true === $val: $this->line .= 'true'; break;
            case false === $val: $this->line .= 'false'; break;
            case INF === $val: $this->line .= '"n`INF"'; break;
            case -INF === $val: $this->line .= '"n`-INF"'; break;
            case is_nan($val): $this->line .= '"n`NAN"'; break;
            case $val > 9007199254740992 && is_int($val): $val = '"n`'.$val.'"'; // JavaScript max integer is 2^53
            default: $this->line .= (string) $val; break;
        }

        $this->endLine($cursor);
    }

    public function dumpString(Cursor $cursor, $str, $bin, $cut)
    {
        if ($this->dumpKey($cursor)) {
            return;
        }
        if ($bin) {
            $str = 'b`'.$str;
            if ($cut) {
                $str = ($cut + iconv_strlen($str, 'UTF-8')).$str;
            }
        } elseif ($cut) {
            $str = ($cut + iconv_strlen($str, 'UTF-8')).'u`'.$str;
        } elseif (false !== strpos($str, '`')) {
            $str = 'u`'.$str;
        }
        $this->line .= $this->encodeString($str);
        $this->endLine($cursor);
    }

    public function enterArray(Cursor $cursor, $count, $indexed, $hasChild)
    {
        if ($indexed && $cursor->depth) {
            if ($this->dumpKey($cursor)) {
                return;
            }
            $this->line .= '[';
            if ($hasChild) {
                $this->dumpLine($cursor->depth);
            }
        } else {
            $this->enterHash($cursor, 'array:'.$count, $hasChild);
        }
    }

    public function leaveArray(Cursor $cursor, $count, $indexed, $hasChild, $cut)
    {
        $this->leaveHash($cursor, $indexed && $cursor->depth ? ']' : '}', $hasChild, $cut);
    }

    public function enterObject(Cursor $cursor, $class, $hasChild)
    {
        $this->enterHash($cursor, $class, $hasChild);
    }

    public function leaveObject(Cursor $cursor, $class, $hasChild, $cut)
    {
        $this->leaveHash($cursor, '}', $hasChild, $cut);
    }

    public function enterResource(Cursor $cursor, $res, $hasChild)
    {
        $this->enterHash($cursor, 'resource:'.$res, $hasChild);
    }

    public function leaveResource(Cursor $cursor, $res, $hasChild, $cut)
    {
        $this->leaveHash($cursor, '}', $hasChild, $cut);
    }

    protected function enterHash(Cursor $cursor, $type, $hasChild)
    {
        if ($this->dumpKey($cursor)) {
            return;
        }

        $this->line .= '{"_":"'.$this->position.':'.$type.'"';
        if ($hasChild) {
            $this->line .= ',';
            $this->dumpLine($cursor->depth);
        }
    }

    protected function leaveHash(Cursor $cursor, $suffix, $hasChild, $cut)
    {
        if (false !== $cursor->refTo) {
            return;
        }
        if (!$hasChild && $cut) {
            $this->line .= ',"__cutBy": '.$cut;
        }
        $this->line .= $suffix;
        $this->endLine($cursor);
    }

    protected function encodeString($str)
    {
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

        $this->line .= '"'.str_replace($map[0], $map[1], $str).'"';
    }

    protected function dumpKey(Cursor $cursor)
    {
        ++$this->position;
        $key = $cursor->hashKey;

        if (null !== $key && ($cursor::HASH_INDEXED !== $cursor->hashType || 1 == $cursor->depth)) {
            if (is_int($key)) {
                $key = 'n`'.$key;
            } else {
                if (!preg_match('//u', $key)) {
                    $key = 'b`'.Data::utf8Encode($key);
                } elseif (false !== strpos($key, '`')) {
                    $key = 'u`'.$key;
                }

                if (isset($key[0]) && "\0" === $key[0] && $cursor::HASH_ASSOC !== $cursor->hashType) {
                    $key = implode(':', explode("\0", substr($key, 1), 2));
                } elseif (isset(static::$reserved[$key]) || false !== strpos($key, ':')) {
                    $key = ':'.$key;
                }
            }

            $this->line .= $this->encodeString($key).': ';
        }
        if (false !== $cursor->refIndex) {
            $this->refsPos[$cursor->refIndex] = $this->position;
        }
        if (false !== $cursor->refTo) {
            $ref = $this->refsPos[$cursor->refTo];
            if ($cursor->refIsHard) {
                $this->refs[$ref][] = -$this->position;
                $ref = 'R`'.$this->position.':'.$ref;
            } else {
                $this->refs[$ref][] = $this->position;
                $ref = 'r`'.$this->position.':'.$ref;
            }
            $this->line .= $this->encodeString($ref);
            $this->endLine($cursor);

            return true;
        }
    }

    public function dumpEnd()
    {
        $this->refsPos = array();
        $this->refs = array();
        $this->position = 0;

        parent::dumpEnd();
    }

    protected function endLine(Cursor $cursor)
    {
        $depth = $cursor->depth;

        if (1 < $cursor->hashLength - $cursor->hashIndex) {
            $this->line .= ',';
        } else {
            if ($cursor::HASH_INDEXED !== $cursor->hashType && $cursor->hashCut) {
                $this->line .= ',';
                $this->dumpLine($depth);
                $this->line .= '"__cutBy": '.$cursor->hashCut;
            }
            if (1 == $depth && $this->refs) {
                $this->line .= ',';
                $this->dumpLine($depth);
                $this->line .= '"__refs": '.json_encode($this->refs);
            }
        }

        $this->dumpLine($depth);
    }
}
