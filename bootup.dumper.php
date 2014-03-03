<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

if (!function_exists('debug'))
{
    function debug($var)
    {
        static $reflector;

        if (!isset($reflector)) {
            $reflector = new ReflectionFunction('set_debug_handler');
        }

        $h = $reflector->getStaticVariables();

        if (isset($h['handler'])) {
            return $h['handler']($var);
        } else {
            var_dump($var);
        }
    }

    function set_debug_handler(\Closure $closure)
    {
        static $handler = null;

        $h = $handler;
        $handler = $closure;

        return $h;
    }
}
