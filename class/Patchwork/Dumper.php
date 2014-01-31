<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork;

class Dumper
{
    protected static $dumpHandler = 'var_dump';

    public static function dump($var)
    {
        $dump = self::$dumpHandler;
        $dump($var);
    }

    public static function setHandler(\Closure $handler)
    {
        $prevHandler = self::$dumpHandler;
        self::$dumpHandler = $handler;

        return 'var_dump' === $prevHandler
            ? function ($var) {var_dump($var);}
            : $prevHandler;
    }
}
