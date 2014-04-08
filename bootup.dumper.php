<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

use Patchwork\Dumper\Collector\PhpCollector;
use Patchwork\Dumper\Dumper\CliDumper;

if (!function_exists('debug')) {
    function debug($var)
    {
        static $reflector;

        if (!isset($reflector)) {
            $reflector = new ReflectionFunction('set_debug_handler');
        }

        $h = $reflector->getStaticVariables();

        if (!isset($h['handler'])) {
            if (class_exists('Patchwork\Dumper\Dumper\CliDumper')) {
                $collector = new PhpCollector;
                $dumper = new CliDumper;
                $h['handler'] = function ($var) use ($collector, $dumper) {
                    $dumper->dump($collector->collect($var));
                };
            } else {
                $h['handler'] = 'var_dump';
            }
            set_debug_handler($h['handler']);
        }

        return $h['handler']($var);
    }

    function set_debug_handler($callable)
    {
        static $handler = null;

        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Invalid PHP callback.');
        }

        $h = $handler;
        $handler = $callable;

        return $h;
    }
}
