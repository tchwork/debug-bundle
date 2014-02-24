<?php
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\DumperBundle\Controller;

use Patchwork\Dumper\JsonDumper;
use Patchwork\Dumper\Caster\ReflectionCaster;
use ReflectionClass;
use ReflectionMethod;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReflectionController extends Controller
{
    /**
     * @Route("/reflection/class", name="reflection_class", defaults={"_format": "json"})
     * @Method({"GET","POST"})
     */
    public function classAction(Request $request)
    {
        $class = new ReflectionClass($request->get('class', 'appDevDebugProjectContainer'));

        $json = '';

        $dumper = new JsonDumper(function ($line, $depth) use (&$json) {
            $json .= str_repeat('  ', $depth) . $line . "\n";
        });

        $dumper->addCasters(
            array(
                'o:ReflectionClass' => array('Patchwork\Dumper\Caster\ReflectionCaster', 'castReflectionClass'),
                'o:ReflectionFunctionAbstract' => array('Patchwork\Dumper\Caster\ReflectionCaster', 'castReflectionFunctionAbstract'),
                'o:ReflectionMethod' => array($this, 'castReflectionMethod'),
            )
        );

        $dumper->walk($class);

        return new Response($json);
    }

    public function castReflectionMethod(ReflectionMethod $c, array $a)
    {
        $a = ReflectionCaster::castReflectionMethod($c, $a);

        unset($a["\0~\0file"]);
        unset($a["\0~\0lines"]);

        return $a;
    }
}
