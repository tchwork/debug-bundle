<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Cursor;

/**
 * JsonDumper implements the JSON convention to dump any PHP variable with high accuracy.
 *
 * @see Resources/doc/json-spec.md
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class JsonDumper extends AbstractDumper
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

    /**
     * {@inheritdoc}
     */
    public function dumpScalar(Cursor $cursor, $type, $val)
    {
        if ($this->dumpKey($cursor)) {
            return;
        }

        switch ($type) {
            case 'NULL': $this->line .= 'null'; break;
            case 'boolean': $this->line .= $val ? 'true' : 'false'; break;
            case 'integer':
                // JavaScript max integer is 2^53
                $this->line .= $val > 9007199254740992 ? '"n`'.$val.'"' : $val;
                break;
            case 'double':
                if (is_nan($val)) {
                    $val = 'NAN';
                } elseif (INF === $val) {
                    $val = 'INF';
                } elseif (-INF === $val) {
                    $val = '-INF';
                } else {
                    $this->line .= json_encode($val);
                    break;
                }
                $this->line .= '"n`'.$val.'"';
                break;
            default:
                $this->line .= '"n`'.$type.'"';
                break;
        }

        $this->endLine($cursor);
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function enterHash(Cursor $cursor, $type, $class, $hasChild)
    {
        if ($this->dumpKey($cursor)) {
            return;
        }

        if (Cursor::HASH_INDEXED === $type && $cursor->depth) {
            $this->line .= '[';
            if ($hasChild) {
                $this->dumpLine($cursor->depth);
            }

            return;
        }

        if (Cursor::HASH_OBJECT === $type) {
            $type = $class;
        } elseif (Cursor::HASH_RESOURCE === $type) {
            $type = 'resource:'.$class;
        } else {
            $type = 'array:'.$class;
        }

        $this->line .= '{"_":';
        $type = $this->position.':'.$type;
        if (!preg_match('//u', $type)) {
            $type = 'b`'.Data::utf8Encode($type);
        } elseif (false !== strpos($type, '`')) {
            $type = 'u`'.$type;
        }
        $this->encodeString($type);
        if ($hasChild) {
            $this->line .= ',';
            $this->dumpLine($cursor->depth);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveHash(Cursor $cursor, $type, $class, $hasChild, $cut)
    {
        if ($cursor->hardRefTo && $cursor->hardRefTo !== $cursor->refIndex) {
            return;
        }
        if ($cursor->softRefTo && $cursor->softRefTo !== $cursor->refIndex) {
            return;
        }
        if (!$hasChild && $cut) {
            $this->line .= ',"__cutBy": '.$cut;
        }
        $this->line .= Cursor::HASH_INDEXED === $type && $cursor->depth ? ']' : '}';
        $this->endLine($cursor);
    }

    /**
     * JSON-encodes a string.
     */
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

    /**
     * Dumps a key in a hash structure.
     *
     * @param Cursor $cursor The Cursor position in the dump.
     */
    protected function dumpKey(Cursor $cursor)
    {
        ++$this->position;
        $key = $cursor->hashKey;

        if (null !== $key && ($cursor::HASH_INDEXED !== $cursor->hashType || 1 == $cursor->depth)) {
            if (is_int($key)) {
                $key = 'n`'.$key;
            } else {
                if (isset($key[0]) && "\0" === $key[0] && $cursor::HASH_ASSOC !== $cursor->hashType) {
                    $key = implode(':', explode("\0", substr($key, 1), 2));
                } elseif (isset(static::$reserved[$key]) || false !== strpos($key, ':')) {
                    $key = ':'.$key;
                }
                if ($cursor->hashKeyIsBinary) {
                    $key = 'b`'.$key;
                } elseif (false !== strpos($key, '`')) {
                    $key = 'u`'.$key;
                }
            }

            $this->line .= $this->encodeString($key).': ';
        }
        if ($cursor->refIndex) {
            $this->refsPos[$cursor->refIndex] = $this->position;
        }
        $ref = false;
        if ($cursor->hardRefTo && $cursor->hardRefTo !== $cursor->refIndex) {
            $ref = $this->refsPos[$cursor->hardRefTo];
            $this->refs[$ref][] = -$this->position;
            $ref = 'R`'.$this->position.':'.$ref;
        } elseif ($cursor->softRefTo && $cursor->softRefTo !== $cursor->refIndex) {
            $ref = $this->refsPos[$cursor->softRefTo];
            $this->refs[$ref][] = $this->position;
            $ref = 'r`'.$this->position.':'.$ref;
        }
        if (false !== $ref) {
            $this->line .= $this->encodeString($ref);
            $this->endLine($cursor);

            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dumpLine($depth)
    {
        parent::dumpLine($depth);

        if (-1 === $depth) {
            $this->refsPos = array();
            $this->refs = array();
            $this->position = 0;
        }
    }

    /**
     * Finishes a line and dumps it.
     *
     * @param Cursor $cursor The current Cursor position.
     */
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
