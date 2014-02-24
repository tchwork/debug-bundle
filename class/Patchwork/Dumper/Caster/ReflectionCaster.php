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
    const META_PREFIX = "\0~\0";

    static function castReflectionClass(ReflectionClass $c, array $a)
    {
        $a = array();
        $m = self::META_PREFIX;

        if ($c->getModifiers()) {
            $a[$m . 'modifiers'] = implode(' ', Reflection::getModifierNames($c->getModifiers()));
        }

        if (false === $a[$m . 'file'] = $c->getFileName()) {
            unset($a[$m . 'file']);
        } else {
            $a[$m . 'lines'] = $c->getStartLine() . '-' . $c->getEndLine();
        }

        $a[$m . 'methods'] = $c->getMethods();

        return $a;
    }

    static function castReflectionFunctionAbstract(ReflectionFunctionAbstract $c, array $a)
    {
        $a = array();

        foreach ($c->getParameters() as $p)
        {
            $n = strstr($p->__toString(), '>');
            $n = substr($n, 2, strpos($n, ' = ') - 2);

            try
            {
                if (strpos($n, ' or NULL ')) $a[str_replace(' or NULL', '', $n)] = null;
                else if ($p->isDefaultValueAvailable()) $a[$n] = $p->getDefaultValue();
                else $a[] = $n;
            }
            catch (\ReflectionException $p)
            {
                // This will be reached on PHP 5.3.16 because of https://bugs.php.net/62715
                $a[] = $n;
            }
        }

        $m = self::META_PREFIX;
        $a = (array) $c + array(
            $m . 'returnsRef' => true,
            $m . 'args' => $a,
        );
        if (!$c->returnsReference()) unset($a[$m . 'returnsRef']);
        $a[$m . 'use'] = array();

        if (false === $a[$m . 'file'] = $c->getFileName()) unset($a[$m . 'file']);
        else $a[$m . 'lines'] = $c->getStartLine() . '-' . $c->getEndLine();

        if (!$c = $c->getStaticVariables()) unset($a[$m . 'use']);
        else foreach ($c as $p => &$c) $a[$m . 'use']['$' . $p] =& $c;

        return $a;
    }

    static function castReflectionMethod(ReflectionMethod $c, array $a)
    {
        $m = self::META_PREFIX;

        $a[$m . 'modifiers'] = implode(' ', Reflection::getModifierNames($c->getModifiers()));

        return $a;
    }
}
