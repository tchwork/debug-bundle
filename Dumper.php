<?php

namespace Patchwork\PHP;

class Dumper
{
    protected static

    $token,
    $depth,
    $refCount,
    $arrayStack = array(),
    $objectStack = array();


    static function dump($a)
    {
        return self::dumpVar($a);
    }

    static function dumpVar(&$a)
    {
        self::$token = "\x9D" . md5(mt_rand(), true);
        self::$refCount = 0;
        self::$depth = 0;

        $d = self::refDump($a);

        foreach (self::$arrayStack as &$a) unset($a[self::$token]);

        self::$arrayStack = array();
        self::$objectStack = array();

        return $d;
    }

    protected static function refDump(&$a, $ref_check = '1')
    {
        switch (true)
        {
        case true  === $a: return 'true';
        case false === $a: return 'false';
        case null  === $a: return 'null';
        case  INF  === $a: return  'INF';
        case -INF  === $a: return '-INF';
        case NAN   === $a: return 'NAN';

        case is_string($a):
            $ref_check = addcslashes($a, '"');

            if (false !== strpos($a, "\n"))
            {
                $ref_check = "\"\"\n" . $ref_check . "\"\"";
                $ref_check = str_replace("\n", "\n" . str_repeat('  ', self::$depth+1), $ref_check);
            }

            return '"' . $ref_check . '"' ;

        case is_array($a):
            if ($ref_check)
            {
                if (isset($a[self::$token])) return "[#{$a[self::$token]}]";
                $a[self::$token] = ++self::$refCount;
                $ref_check = '#' . $a[self::$token];
                self::$arrayStack[] =& $a;
            }

            $i = 0;
            $b = array();
            ++self::$depth;

            foreach ($a as $k => &$v)
            {
                if (self::$token === $k) continue;
                else if (is_int($k) && 0 <= $k)
                {
                    $b[] = ($k !== $i ? $k . ' => ' : '') . self::refDump($v);
                    $i = $k + 1;
                }
                else
                {
                    if ('' === $ref_check && isset($k[0]))
                    {
                        if ("\0" === $k[0]) $k = implode(':', explode("\0", substr($k, 1), 2));
                        else if (false !== strpos($k, ':')) $k = ':' . $k;
                    }

                    $b[] = self::refDump($k) . ' => ' . self::refDump($v);
                }
            }

            $k = str_repeat('  ', self::$depth);
            --self::$depth;

            return $ref_check . '[' . ($b ? "\n{$k}" . implode(",\n{$k}", $b) . "\n" . substr($k, 2) : '') . ']';

        case is_object($a):
            $h = spl_object_hash($a);
            $c = get_class($a);

            if (isset(self::$objectStack[$h]))
            {
                $ref_check = '{#' . self::$objectStack[$h];
                $h = '';
            }
            else
            {
                self::$objectStack[$h] = ++self::$refCount;
                $ref_check = '#' . self::$objectStack[$h] . '{';

                $h = (array) $a;
                $h = substr(self::refDump($h, ''), 1, -1);
            }

            return ('stdClass' !== $c ? $c : '') . $ref_check . $h . '}';

        case is_resource($a):
            return ((string) $a) . ' (' . get_resource_type($a) . ')';

        // float and integer
        default: return (string) $a;
        }
    }
}
