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

use Doctrine\Common\Proxy\Proxy as CommonProxy;
use Doctrine\ORM\Proxy\Proxy as OrmProxy;

/**
 * Casts Doctrine related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DoctrineCaster
{
    public static function castCommonProxy(CommonProxy $p, array $a)
    {
        unset(
            $a['__cloner__'],
            $a['__initializer__']
        );

        return $a;
    }

    public static function castOrmProxy(OrmProxy $p, array $a)
    {
        $p = "\0".get_class($p)."\0";
        unset(
            $a[$p.'_entityPersister'],
            $a[$p.'_identifier']
        );

        return $a;
    }
}
