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


    static function dumpConst($a)
    {
        return self::dump($a, false);
    }

    static function dump(&$a, $ref = true)
    {
        self::$token = "\x9D" . md5(mt_rand(), true);
        self::$refCount = 0;
        self::$depth = 0;

        $d = self::refDump($a, $ref ? '1' : '');

        foreach (self::$arrayStack as &$a) unset($a[self::$token]);

        self::$arrayStack = array();
        self::$objectStack = array();

        return $d;
    }

    protected static function refDump(&$a, $ref = '1')
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
            $ref = addcslashes($a, '"');

            if (false !== strpos($a, "\n"))
            {
                $ref = "\"\"\n" . $ref . "\"\"";
                $ref = str_replace("\n", "\n" . str_repeat('  ', self::$depth+1), $ref);
            }

            return '"' . $ref . '"' ;

        case is_array($a):
            if ($ref)
            {
                if (isset($a[self::$token])) return "[#{$a[self::$token]}]";
                $a[self::$token] = ++self::$refCount;
                $ref = '#' . $a[self::$token];
                self::$arrayStack[] =& $a;
            }

            $i = 0;
            $b = array();
            ++self::$depth;

            foreach ($a as $k => &$v)
            {
                if (is_int($k) && 0 <= $k)
                {
                    $b[] = ($k !== $i ? $k . ' => ' : '') . self::refDump($v);
                    $i = $k + 1;
                }
                else
                {
                    if ('' === $ref)
                    {
                        if (isset($k[0]) && "\0" === $k[0]) $k = implode(':', explode("\0", substr($k, 1), 2));
                        else if (false !== strpos($k, ':')) $k = ':' . $k;
                    }
                    else if (self::$token === $k) continue;

                    $b[] = self::refDump($k) . ' => ' . self::refDump($v);
                }
            }

            $k = str_repeat('  ', self::$depth);
            --self::$depth;

            return $ref . '[' . ($b ? "\n{$k}" . implode(",\n{$k}", $b) . "\n" . substr($k, 2) : '') . ']';

        case is_object($a):
            $h = spl_object_hash($a);
            $c = get_class($a);
            $ref = 'stdClass' !== $c ? $c : '';

            if (isset(self::$objectStack[$h]))
            {
                $ref .= '{#' . self::$objectStack[$h];
                $h = '';
            }
            else
            {
                self::$objectStack[$h] = ++self::$refCount;
                $ref .= '#' . self::$objectStack[$h] . '{';

                if ($a instanceof \Closure && class_exists('ReflectionFunction', false))
                {
                    $r = new \ReflectionFunction($a);
                    $h = array();
                    $r->returnsReference() && $h[] = '&';

                    foreach ($r->getParameters() as $c)
                    {
                        $n = ($c->isPassedByReference() ? '&$' : '$') . $c->getName();

                        if ($c->isDefaultValueAvailable()) $h[$n] = $c->getDefaultValue();
                        else $h[] = $n;
                    }

                    $h['use'] = array();

                    if (method_exists($r, 'getClosureThis')) $h['this'] = $r->getClosureThis();

                    if (false === $h['file'] = $r->getFileName()) unset($h['file']);
                    else $h['lines'] = $r->getStartLine() . '-' . $r->getEndLine();

                    if (!$r = $r->getStaticVariables()) unset($h['use']);
                    else foreach ($r as $c => &$r) $h['use']['$' . $c] =& $r;

                }
                else $h = (array) $a;

                $h = substr(self::refDump($h, ''), 1, -1);
            }

            return $ref . $h . '}';

        case is_resource($a):
            return ((string) $a) . ' (' . get_resource_type($a) . ')';

        // float and integer
        default: return (string) $a;
        }
    }
}
