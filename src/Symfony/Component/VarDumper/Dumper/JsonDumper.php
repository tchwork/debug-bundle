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
        if ('string' === $type) {
            return $this->dumpString($cursor, $val, false, 0);
        }

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
                // No break;
            default:
            case 'const':
                $this->line .= '"n`'.$val.'"';
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

    /**
     * {@inheritdoc}
     */
    public function leaveArray(Cursor $cursor, $count, $indexed, $hasChild, $cut)
    {
        $this->leaveHash($cursor, $indexed && $cursor->depth ? ']' : '}', $hasChild, $cut);
    }

    /**
     * {@inheritdoc}
     */
    public function enterObject(Cursor $cursor, $class, $hasChild)
    {
        $this->enterHash($cursor, $class, $hasChild);
    }

    /**
     * {@inheritdoc}
     */
    public function leaveObject(Cursor $cursor, $class, $hasChild, $cut)
    {
        $this->leaveHash($cursor, '}', $hasChild, $cut);
    }

    /**
     * {@inheritdoc}
     */
    public function enterResource(Cursor $cursor, $res, $hasChild)
    {
        $this->enterHash($cursor, 'resource:'.$res, $hasChild);
    }

    /**
     * {@inheritdoc}
     */
    public function leaveResource(Cursor $cursor, $res, $hasChild, $cut)
    {
        $this->leaveHash($cursor, '}', $hasChild, $cut);
    }

    /**
     * Generic dumper used while entering any hash-style structure.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param string $type     The type of the hash structure.
     * @param bool   $hasChild When the dump of the hash has child item.
     */
    protected function enterHash(Cursor $cursor, $type, $hasChild)
    {
        if ($this->dumpKey($cursor)) {
            return;
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
     * Generic dumper used while leaving any hash-style structure.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param string $suffix   The string that ends the next dumped line.
     * @param bool   $hasChild When the dump of the hash has child item.
     * @param int    $cut      The number of items the hash has been cut by.
     */
    protected function leaveHash(Cursor $cursor, $suffix, $hasChild, $cut)
    {
        if (false !== $cursor->softRefTo || false !== $cursor->hardRefTo) {
            return;
        }
        if (!$hasChild && $cut) {
            $this->line .= ',"__cutBy": '.$cut;
        }
        $this->line .= $suffix;
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
        if (false !== $cursor->hardRefTo) {
            $ref = $this->refsPos[$cursor->hardRefTo];
            $this->refs[$ref][] = -$this->position;
            $ref = 'R`'.$this->position.':'.$ref;
        } elseif (false !== $cursor->softRefTo) {
            $ref = $this->refsPos[$cursor->softRefTo];
            $this->refs[$ref][] = $this->position;
            $ref = 'r`'.$this->position.':'.$ref;
        }
        if (false !== $cursor->softRefTo || false !== $cursor->hardRefTo) {
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
