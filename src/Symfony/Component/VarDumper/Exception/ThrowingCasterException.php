<?php

/*
* This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Component\VarDumper\Exception;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ThrowingCasterException extends \Exception
{
    private $caster;

    public function __construct($caster, \Exception $prev)
    {
        $this->caster = $caster;
        parent::__construct(null, 0, $prev);
    }
}
