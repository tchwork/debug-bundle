<?php
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper\Caster;

use Reflection;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class ReflectionCaster
{
    public static function castReflector(\Reflector $c, array $a)
    {
        $a["\0~\0reflection"] = $c->__toString();

        return $a;
    }

    public static function castClosure(\Closure $c, array $a)
    {
        $a = static::castReflector(new \ReflectionFunction($c), $a);
        unset($a[0], $a['name']);

        return $a;
    }

    public static function castReflectionClass(ReflectionClass $c, array $a)
    {
        $a = array();

        if ($m = $c->getModifiers()) {
            $a["\0~\0modifiers"] = implode(' ', Reflection::getModifierNames($m));
        }

        if (false === $a["\0~\0file"] = $c->getFileName()) {
            unset($a["\0~\0file"]);
        } else {
            $a["\0~\0lines"] = $c->getStartLine() . '-' . $c->getEndLine();
        }

        $a["\0~\0methods"] = $c->getMethods();

        return $a;
    }

    public static function castReflectionFunctionAbstract(ReflectionFunctionAbstract $c, array $a)
    {
        $a = array();

        foreach ($c->getParameters() as $p) {
            $n = strstr($p->__toString(), '>');
            $n = substr($n, 2, strpos($n, ' = ') - 2);

            try {
                if (strpos($n, ' or NULL ')) {
                    $a[str_replace(' or NULL', '', $n)] = null;
                } elseif ($p->isDefaultValueAvailable()) {
                    $a[$n] = $p->getDefaultValue();
                } else {
                    $a[] = $n;
                }
            } catch (\ReflectionException $p) {
                // This will be reached on PHP 5.3.16 because of https://bugs.php.net/62715
                $a[] = $n;
            }
        }

        $a = (array) $c + array(
            "\0~\0returnsRef" => true,
            "\0~\0args" => $a,
        );
        if (!$c->returnsReference()) {
            unset($a["\0~\0returnsRef"]);
        }
        $a["\0~\0use"] = array();

        if (false === $a["\0~\0file"] = $c->getFileName()) {
            unset($a["\0~\0file"]);
        } else {
            $a["\0~\0lines"] = $c->getStartLine() . '-' . $c->getEndLine();
        }

        if (!$c = $c->getStaticVariables()) {
            unset($a["\0~\0use"]);
        } else {
            foreach ($c as $p => &$c) {
                $a["\0~\0use"]['$' . $p] =& $c;
            }
        }

        return $a;
    }

    public static function castReflectionMethod(ReflectionMethod $c, array $a)
    {
        $a["\0~\0modifiers"] = implode(' ', Reflection::getModifierNames($c->getModifiers()));

        return $a;
    }
}
