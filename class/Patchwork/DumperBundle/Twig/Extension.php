<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\DumperBundle\Twig;

use Patchwork\Dumper\VarDebug;

class Extension extends \Twig_Extension
{
    public function getTokenParsers()
    {
        return array(new DebugTokenParser());
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('debug', array($this, 'debug'), array('is_safe' => array('html'), 'needs_context' => true, 'needs_environment' => true)),
        );
    }

    public function getName()
    {
        return 'patchwork_dumper';
    }

    public function debug(\Twig_Environment $env, $context)
    {
        if (!$env->isDebug()) {
            return;
        }

        $count = func_num_args();
        if (2 === $count) {
            $vars = array();
            foreach ($context as $key => $value) {
                if (!$value instanceof \Twig_Template) {
                    $vars[$key] = $value;
                }
            }
        } elseif (3 === $count) {
            $vars = func_get_arg(2);
        } else {
            $vars = array_slice(func_get_args(), 2);
        }

        VarDebug::debug($vars);

        return '';
    }
}
