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
use Patchwork\Dumper\Dumper\HtmlDumper;
use Patchwork\Dumper\Dumper\CliDumper;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

class DataCollector extends BaseDataCollector
{
    private $rootDir;
    private $stopwatch;
    private $isCollected = true;

    public function __construct($rootDir, Stopwatch $stopwatch = null)
    {
        $this->rootDir = dirname($rootDir).DIRECTORY_SEPARATOR;
        $this->stopwatch = $stopwatch;
        $this->data['dumps'] = array();
    }

    public function dump(Data $data)
    {
        $this->stopwatch and $this->stopwatch->start('debug');
        $this->isCollected = false;

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
                            $file = false;
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
        $this->isCollected = true;
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

    public function serialize()
    {
        $this->isCollected = true;

        return parent::serialize();
    }

    public function __destruct()
    {
        if (!$this->isCollected) {
            $this->isCollected = true;

            $h = headers_list();
            array_unshift($h, 'Content-Type: ' . ini_get('default_mimetype'));
            $i = count($h);
            while (0 !== stripos($h[--$i], 'Content-Type:')) {
            }

            if (stripos($h[$i], 'html')) {
                echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
                $dumper = new HtmlDumper();
            } else {
                $dumper = new CliDumper('php://output');
                $dumper->setColors(false);
            }

            foreach ($this->data['dumps'] as $i => $dump) {
                $this->data['dumps'][$i] = null;

                if ($dumper instanceof HtmlDumper) {
                    $dump['name'] = htmlspecialchars($dump['name'], ENT_QUOTES, 'UTF-8');
                    $dump['file'] = htmlspecialchars($dump['file'], ENT_QUOTES, 'UTF-8');
                    if ('' !== $dump['file']) {
                        $dump['name'] = "<abbr title=\"{$dump['file']}\">{$dump['name']}</abbr>";
                    }
                    echo "\n<br><span class=\"sf-var-debug-meta\">in {$dump['name']} on line {$dump['line']}:</span>";
                } else {
                    echo "\nin {$dump['name']} on line {$dump['line']}:\n\n";
                }
                $dumper->dump($dump['data']);
            }

            $this->data = array();
        }
    }
}
