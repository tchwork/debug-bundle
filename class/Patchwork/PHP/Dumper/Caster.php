<?php /****************** vi: set fenc=utf-8 ts=4 sw=4 et: *****************
 *
 *   Copyright : (C) 2012 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/lgpl.txt GNU/LGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Lesser General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/

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
        $a = array($m . 'args' => $a);
        if ($c->returnsReference()) $a[$m . 'returnsReference'] = true;
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
        $a = (array) $c;
        $m = self::META_PREFIX;
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
                $a[$m . $attr] = 'ERRMODE' === $attr ? $errmode : $c->getAttribute(constant("PDO::ATTR_{$attr}"));
                if (isset($values[$attr][$a[$m . $attr]])) $a[$m . $attr] = $values[$attr][$a[$m . $attr]];
            }
            catch (\Exception $attr)
            {
            }
        }

        $c->setAttribute(\PDO::ATTR_ERRMODE, $errmode);

        return $a;
    }
}
