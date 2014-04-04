<?php

namespace Patchwork\Dumper;

abstract class AbstractDumper
{
    public static $defaultOutputStream = 'php://output';

    protected $line = '';
    protected $lineDumper = array(__CLASS__, 'echoLine');
    protected $outputStream;

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
    }

    public function setLineDumper($callback)
    {
        $prev = $this->lineDumper;
        $this->lineDumper = $callback;

        return $prev;
    }

    public function dump(Collector\Data $data, $lineDumper = null)
    {
        isset($lineDumper) and $lineDumper = $this->setLineDumper($lineDumper);
        $data->dump($this);
        isset($lineDumper) and $this->setLineDumper($lineDumper);
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
