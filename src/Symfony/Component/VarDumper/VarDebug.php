<?php

namespace Patchwork\Dumper;

use Patchwork\Dumper\Collector\PhpCollector;
use Patchwork\Dumper\Dumper\CliDumper;
use Patchwork\Dumper\Dumper\HtmlDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class VarDebug
{
    private static $handlerObject;
    private static $handler;

    public static function debug($var)
    {
        if (!isset(self::$handler)) {
            $collector = new PhpCollector();
            $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();
            self::$handler = function ($var) use ($collector, $dumper) {
                $dumper->dump($collector->collect($var));
            };
        }

        $h = self::$handler;

        if (isset(self::$objectHandler)) {
            $obj = self::$objectHandler;

            return $obj->$h($var);
        }

        return $h($var);
    }

    public static function setHandler($callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Invalid PHP callback.');
        }

        $prevHandler = self::$handler;

        if (isset(self::$objectHandler)) {
            $prevHandler = array(self::$objectHandler, $prevHandler);
            self::$objectHandler = null;
        }

        if (is_array($callable)) {
            if (is_string($callable[0])) {
                self::$handler = $callable[0].'::'.$callable[1];
            } else {
                self::$objectHandler = $callable[0];
                self::$handler = $callable[1];
            }
        } else {
            self::$handler = $callable;
        }

        return $prevHandler;
    }
}
