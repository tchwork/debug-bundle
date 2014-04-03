<?php

namespace Patchwork\Dumper;

abstract class AbstractDumper
{
    public static $defaultOutputStream = 'php://output';

    public $maxItems;
    public $maxString;

    protected $line = '';
    protected $lineDumper = array(__CLASS__, 'echoLine');
    protected $outputStream;

    private $collector;

    public function __construct($outputStream = null)
    {
        if (is_callable($outputStream)) {
            $this->setLineDumper($outputStream);
        } else {
            isset($outputStream) or $outputStream =& static::$defaultOutputStream;
            is_string($outputStream) and $outputStream = fopen($outputStream, 'wb');
            $this->outputStream = $outputStream;
            $this->setLineDumper(array($this, 'echoLine'));
        }

        $this->collector = extension_loaded('symfony_debug')
            ? new Collector\SymfonyCollector()
            : new Collector\PhpCollector();

        $this->maxItems =& $this->collector->maxItems;
        $this->maxString =& $this->collector->maxString;
    }

    public function addCaster(array $casters)
    {
        $this->collector->addCasters($casters);
    }

    public function setLineDumper($callback)
    {
        $prev = $this->lineDumper;
        $this->lineDumper = $callback;

        return $prev;
    }

    public static function dump($var)
    {
        $dumper = new static;
        $dumper->walk($var);
    }

    public function walk($var)
    {
        $c = new CollectorDumper($this->collector, $this);
        $c->dump($var);
        '' !== $this->line && $this->dumpLine(0);
        $this->dumpLine(false); // Notifies end of dump
    }

    protected function dumpLine($depth)
    {
        call_user_func($this->lineDumper, $this->line, $depth);
        $this->line = '';
    }

    protected function echoLine($line, $depth)
    {
        fwrite($this->outputStream, str_repeat('  ', $depth).$line."\n");
    }
}
