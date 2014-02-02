<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\PHP\Dumper;

use Patchwork\PHP\InDepthRecoverableErrorException as InDepthException;

class ExceptionCaster
{
    static public

    $errorTypes = array(
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
    );

    static function castException(\Exception $e, array $a)
    {
        $trace = $a["\0Exception\0trace"];
        unset($a["\0Exception\0trace"]); // Ensures the trace is always last

        static::filterTrace($trace, $e instanceof InDepthException ? $e->traceOffset : 0, 1);

        if (isset($trace)) $a["\0Exception\0trace"] = $trace;
        if (empty($a["\0Exception\0previous"])) unset($a["\0Exception\0previous"]);
        unset($a["\0Exception\0string"], $a['xdebug_message'], $a['__destructorException']);

        return $a;
    }

    static function castErrorException(\ErrorException $e, array $a)
    {
        if (isset($a[$s = "\0*\0severity"], self::$errorTypes[$a[$s]])) $a[$s] = self::$errorTypes[$a[$s]];

        return $a;
    }

    static function castInDepthException(InDepthException $e, array $a)
    {
        unset($a['traceOffset']);

        if (! isset($a['context'])) unset($a['context']);
        else if (isset($a["\0Exception\0trace"]['seeHash']))
        {
            $a['context'] = $a["\0Exception\0trace"];
        }

        return $a;
    }

    static function filterTrace(&$trace, $offset, $dumpArgs)
    {
        if (0 > $offset || empty($trace[$offset])) return $trace = null;

        $t = $trace[$offset];

        if (empty($t['class']) && isset($t['function']))
            if ('user_error' === $t['function'] || 'trigger_error' === $t['function'])
                ++$offset;

        $offset && array_splice($trace, 0, $offset);

        foreach ($trace as &$t)
        {
            $offset = (isset($t['class']) ? $t['class'] . $t['type'] : '')
                . $t['function'] . '()'
                . (isset($t['line']) ? " {$t['file']}:{$t['line']}" : '');

            if (! isset($t['args']) || ! $dumpArgs) $t = array();
            else $t = array('args' => $t['args']);

            $t = array('call' => $offset) + $t;
        }
    }
}
