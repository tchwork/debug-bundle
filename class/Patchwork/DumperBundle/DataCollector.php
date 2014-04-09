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

use Patchwork\Dumper\Collector\Data;
use Patchwork\Dumper\Dumper\JsonDumper;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

class DataCollector extends BaseDataCollector
{
    private $rootDir;
    private $stopwatch;

    public function __construct($rootDir, Stopwatch $stopwatch = null)
    {
        $this->rootDir = dirname($rootDir).DIRECTORY_SEPARATOR;
        $this->stopwatch = $stopwatch;
        $this->data['dumps'] = array();
    }

    public function dump(Data $data)
    {
        $this->stopwatch and $this->stopwatch->start('debug');

        $trace = PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS : true;
        if (PHP_VERSION_ID >= 50400) {
            $trace = debug_backtrace($trace, 6);
        } else {
            $trace = debug_backtrace($trace);
        }

        $file = $trace[0]['file'];
        $line = $trace[0]['line'];
        $name = false;
        $excerpt = false;

        for ($i = 1; $i < 6; ++$i) {
            if (isset($trace[$i]['function']) && 'debug' === $trace[$i]['function'] && empty($trace[$i]['class'])) {
                $file = $trace[$i]['file'];
                $line = $trace[$i]['line'];

                while (++$i < 6) {
                    if (isset($trace[$i]['object']) && $trace[$i]['object'] instanceof \Twig_Template) {
                        $info = $trace[$i]['object'];
                        $name = $info->getTemplateName();
                        $src = $info->getEnvironment()->getLoader()->getSource($name);
                        $info = $info->getDebugInfo();
                        if (isset($info[$trace[$i-1]['line']])) {
                            $line = $info[$trace[$i-1]['line']];
                            $src = explode("\n", $src);
                            $excerpt = array();

                            for ($i = max($line - 3, 1), $max = min($line + 3, count($src)); $i <= $max; ++$i) {
                                $excerpt[] = '<li'.($i === $line ? ' class="selected"' : '').'><code>'.htmlspecialchars($src[$i - 1]).'</code></li>';
                            }

                            $excerpt = '<ol start="'.max($line - 3, 1).'">'.implode("\n", $excerpt).'</ol>';
                        }
                        break;
                    }
                }
                break;
            }
        }

        $name or $name = 0 === strpos($file, $this->rootDir) ? substr($file, strlen($this->rootDir)) : $file;

        $this->data['dumps'][] = compact('data', 'name', 'file', 'line', 'excerpt');

        $this->stopwatch and $this->stopwatch->stop('debug');
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    public function getDumps()
    {
        $dumper = new JsonDumper();
        $dumps = array();

        foreach ($this->data['dumps'] as $dump) {
            $json = '';
            $dumper->dump($dump['data'], function ($line) use (&$json) {$json .= $line;});
            $dump['json'] = $json;
            $dumps[] = $dump;
        }

        return $dumps;
    }

    public function getName()
    {
        return 'patchwork_dumper';
    }
}
