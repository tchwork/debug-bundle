<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper\Caster;

class SplCaster
{
    static function castIterator(\Iterator $c, array $a)
    {
        $a = array_merge($a, iterator_to_array($c));

        return $a;
    }

    static function castSplDoublyLinkedList(\SplDoublyLinkedList $c, array $a)
    {
        $mode = $c->getIteratorMode();
        $c->setIteratorMode(\SplDoublyLinkedList::IT_MODE_KEEP | $mode & ~\SplDoublyLinkedList::IT_MODE_DELETE);
        $a = array_merge($a, iterator_to_array($c));
        $c->setIteratorMode($mode);

        return $a;
    }

    static function castSplFixedArray(\SplFixedArray $c, array $a)
    {
        $a = array_merge($a, $c->toArray());

        return $a;
    }

    static function castSplObjectStorage(\SplObjectStorage $c, array $a)
    {
        foreach ($c as $k => $obj)
        {
            $a[$k] = $obj;
            if (null !== $i = $c->getInfo()) $a["\0~\0$k"] = $i;
        }

        return $a;
    }
}

