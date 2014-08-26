<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\VarDumper\Cloner\ExtCloner;
use Symfony\Component\VarDumper\Cloner\PhpCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugBundle extends Bundle
{
    private static $handler;

    public function boot()
    {
        if ($this->container->getParameter('kernel.debug')) {
            $container = $this->container;

            // This code is here to lazy load the dump stack. This default
            // configuration for CLI mode is overridden in HTTP mode on
            // 'kernel.request' event
            static::setHandler(function ($var) use ($container) {
                $dumper = new CliDumper();
                $cloner = $container->get('var_dumper.cloner');
                $handler = function ($var) use ($dumper, $cloner) {
                    $dumper->dump($cloner->cloneVar($var));
                };
                static::setHandler($handler);
                $handler($var);
            });
        }
    }

    public static function dump($var)
    {
        if (null === self::$handler) {
            $cloner = extension_loaded('symfony_debug') ? new ExtCloner() : new PhpCloner();
            $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();
            self::$handler = function ($var) use ($cloner, $dumper) {
                $dumper->dump($cloner->cloneVar($var));
            };
        }

        $h = self::$handler;

        if (is_array($h)) {
            return $h[0]->{$h[1]}($var);
        }

        return $h($var);
    }

    public static function setHandler($callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Invalid PHP callback.');
        }

        $prevHandler = self::$handler;

        if (is_array($callable)) {
            if (!is_object($callable[0])) {
                self::$handler = $callable[0].'::'.$callable[1];
            }
        } else {
            self::$handler = $callable;
        }

        return $prevHandler;
    }
}
