<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\DumperBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PatchworkDumperBundle extends Bundle
{
    public function boot()
    {
        parent::boot();

        $container = $this->container;
        $dumper = in_array(PHP_SAPI, array('cli', 'cli-server')) ? 'cli' : 'dataCollector';

        set_debug_handler(function ($v) use ($container, $dumper)
        {
            $container->get("patchwork.dumper.$dumper")->walk($v);
        });
    }
}
