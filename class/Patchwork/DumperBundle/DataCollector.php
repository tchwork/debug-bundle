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

use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DataCollector extends BaseDataCollector
{
    private $container;
    private $dumperServiceName;

    public function __construct(ContainerInterface $container, $dumperServiceName)
    {
        $this->container = $container;
        $this->dumperServiceName = $dumperServiceName;
    }

    public function walk(&$var)
    {
        $dumper = $this->container->get($this->dumperServiceName);

        $json = '';
        $lineDumper = $dumper->setLineDumper(function ($line, $depth) use (&$json) {
            $json .= $line;
        });
        $dumper->walk($var);
        $dumper->setLineDumper($lineDumper);

        $this->data['dumps'][] = $json;
    }

    public function setLineDumper()
    {
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    public function getName()
    {
        return 'patchwork.dumper';
    }
}
