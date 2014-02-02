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

class BaseCaster
{
    static function castReflector(\Reflector $c, array $a)
    {
        $a["\0~\0reflection"] = $c->__toString();

        return $a;
    }

    static function castClosure(\Closure $c, array $a)
    {
        $a = static::castReflector(new \ReflectionFunction($c), $a);
        unset($a[0], $a['name']);

        return $a;
    }

    static function castDba($dba, array $a)
    {
        $list = dba_list();
        $a['file'] = $list[substr((string) $dba, 13)];

        return $a;
    }

    static function castProcess($process, array $a)
    {
        return proc_get_status($process);
    }

    static function castStream($stream, array $a)
    {
        return stream_get_meta_data($stream);
    }
}
