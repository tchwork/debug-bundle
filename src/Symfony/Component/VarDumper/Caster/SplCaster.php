<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Casts SPL related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SplCaster
{
    public static function castSplIterator(\Iterator $c, array $a)
    {
        $a = array_merge($a, iterator_to_array(clone $c));

        return $a;
    }

    public static function castSplDoublyLinkedList(\SplDoublyLinkedList $c, array $a)
    {
        $mode = $c->getIteratorMode();
        $c->setIteratorMode(\SplDoublyLinkedList::IT_MODE_KEEP | $mode & ~\SplDoublyLinkedList::IT_MODE_DELETE);
        $a = array_merge($a, iterator_to_array($c));
        $c->setIteratorMode($mode);

        return $a;
    }

    public static function castSplFixedArray(\SplFixedArray $c, array $a)
    {
        $a = array_merge($a, $c->toArray());

        return $a;
    }

    public static function castSplObjectStorage(\SplObjectStorage $c, array $a)
    {
        $storage = array();
        unset($a["\0gcdata"]); // Don't hit https://bugs.php.net/65967

        foreach ($c as $obj) {
            $storage[spl_object_hash($obj)] = array(
                'object' => $obj,
                'info' => $c->getInfo(),
             );
        }

        return $a;
    }
}
