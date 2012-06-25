<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\PHP\Dumper;

/**
 * Caster is a collection of methods each specific to one type of objet for
 * casting to array suitable for extensive dumping by Patchwork\PHP\Dumper.
 */
class Caster
{
    const META_PREFIX = "\0~\0";

    static function castClosure($c)
    {
        $a = array();
        if (!class_exists('ReflectionFunction', false)) return $a;
        $c = new \ReflectionFunction($c);

        foreach ($c->getParameters() as $p)
        {
            $n = ($p->isPassedByReference() ? '&$' : '$') . $p->getName();

            if ($p->isDefaultValueAvailable()) $a[$n] = $p->getDefaultValue();
            else $a[] = $n;
        }

        $m = self::META_PREFIX;
        $a = array(
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


    static $pdoAttributes = array(
        'CASE' => array(
            \PDO::CASE_LOWER => 'LOWER',
            \PDO::CASE_NATURAL => 'NATURAL',
            \PDO::CASE_UPPER => 'UPPER',
        ),
        'ERRMODE' => array(
            \PDO::ERRMODE_SILENT => 'SILENT',
            \PDO::ERRMODE_WARNING => 'WARNING',
            \PDO::ERRMODE_EXCEPTION => 'EXCEPTION',
        ),
        'TIMEOUT',
        'PREFETCH',
        'AUTOCOMMIT',
        'PERSISTENT',
        'DRIVER_NAME',
        'SERVER_INFO',
        'ORACLE_NULLS' => array(
            \PDO::NULL_NATURAL => 'NATURAL',
            \PDO::NULL_EMPTY_STRING => 'EMPTY_STRING',
            \PDO::NULL_TO_STRING => 'TO_STRING',
        ),
        'CLIENT_VERSION',
        'SERVER_VERSION',
        'STATEMENT_CLASS',
        'EMULATE_PREPARES',
        'CONNECTION_STATUS',
        'STRINGIFY_FETCHES',
        'DEFAULT_FETCH_MODE' => array(
            \PDO::FETCH_ASSOC => 'ASSOC',
            \PDO::FETCH_BOTH => 'BOTH',
            \PDO::FETCH_LAZY => 'LAZY',
            \PDO::FETCH_NUM => 'NUM',
            \PDO::FETCH_OBJ => 'OBJ',
        ),
    );

    static function castPdo($c)
    {
        $a = array();
        $errmode = $c->getAttribute(\PDO::ATTR_ERRMODE);
        $c->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        foreach (self::$pdoAttributes as $attr => $values)
        {
            if (!isset($attr[0]))
            {
                $attr = $values;
                $values = array();
            }

            try
            {
                $a[$attr] = 'ERRMODE' === $attr ? $errmode : $c->getAttribute(constant("PDO::ATTR_{$attr}"));
                if (isset($values[$attr][$a[$attr]])) $a[$attr] = $values[$attr][$a[$attr]];
            }
            catch (\Exception $attr)
            {
            }
        }

        $m = self::META_PREFIX;

        $a = (array) $c + array(
            $m . 'errorInfo' => $c->errorInfo(),
            $m . 'attributes' => $a,
        );

        if (!isset($a[$m . 'errorInfo'][1], $a[$m . 'errorInfo'][2])) unset($a[$m . 'errorInfo']);

        $c->setAttribute(\PDO::ATTR_ERRMODE, $errmode);

        return $a;
    }

    static function castPdoStatement($c)
    {
        $m = self::META_PREFIX;
        $a = (array) $c + array($m . 'errorInfo' => $c->errorInfo());
        if (!isset($a[$m . 'errorInfo'][1], $a[$m . 'errorInfo'][2])) unset($a[$m . 'errorInfo']);
        return $a;
    }
}
