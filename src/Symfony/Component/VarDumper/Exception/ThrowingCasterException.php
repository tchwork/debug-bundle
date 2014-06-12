<?php

namespace Patchwork\Dumper\Exception;

class ThrowingCasterException extends \Exception
{
    private $caster;

    public function __construct($caster, \Exception $prev)
    {
        $this->caster = $caster;
        parent::__construct(null, 0, $prev);
    }
}
