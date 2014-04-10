<?php

namespace Patchwork\Dumper\Dumper;

/**
 * HtmlDumper dumps variable as HTML.
 */
class HtmlDumper extends CliDumper
{
    public static $defaultOutputStream = 'php://output';

    public $dumpPrefix;
    public $dumpSuffix;

    protected $colors = true;
    protected $lastDepth = -1;
    protected $styles = array(
        'num'       => 'font-weight:bold;color:#0087FF',
        'const'     => 'font-weight:bold;color:#0087FF',
        'str'       => 'font-weight:bold;color:#00D7FF',
        'cchr'      => 'font-style: italic',
        'note'      => 'color:#D7AF00',
        'ref'       => 'color:#444444',
        'public'    => 'color:#008700',
        'protected' => 'color:#D75F00',
        'private'   => 'color:#D70000',
        'meta'      => 'color:#005FFF',
    );

    public function __construct($outputStream = null)
    {
        parent::__construct($outputStream);

        $this->setStyles($this->styles);
    }

    public function setStyles(array $styles)
    {
        $this->styles = $styles + $this->styles;

        $p = 'sf-var-debug';
        $s = "a.$p-ref{{$this->styles['ref']}}";

        foreach ($this->styles as $class => $style) {
            $s .= "span.$p-$class{{$style}}";
        }

        $this->dumpPrefix = "<style>$s</style><pre class=$p style=white-space:pre>";
        $this->dumpSuffix = '</pre>';
    }

    protected function style($style, $val)
    {
        if ('ref' === $style) {
            $ref = substr($val, 1);
            if ('#' === $val[0]) {
                return "<a class=sf-var-debug-ref name=\"sf-var-debug-ref$ref\">$val</a>";
            } else {
                return "<a class=sf-var-debug-ref href=\"#sf-var-debug-ref$ref\">$val</a>";
            }
        }

        $val = htmlspecialchars($val, ENT_NOQUOTES, 'UTF-8');

        switch ($style) {
            case 'str':
            case 'public':
                static $cchr = array(
                    "\x1B",
                    "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
                    "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
                    "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
                    "\x18", "\x19", "\x1A", "\x1C", "\x1D", "\x1E", "\x1F", "\x7F",
                );

                foreach ($cchr as $c) {
                    if (false !== strpos($val, $c)) {
                        $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                        $val = str_replace($c, "<span class=sf-var-debug-cchr>$r</span>", $val);
                    }
                }
        }

        return "<span class=sf-var-debug-$style>$val</span>";
    }

    protected function dumpLine($depth)
    {
        switch ($this->lastDepth - $depth) {
            case +1: $this->line = '</span>'.$this->line; break;
            case -1: $this->line = "<span class=sf-var-debug-$depth>$this->line"; break;
        }

        if (-1 === $this->lastDepth) {
            $this->line = $this->dumpPrefix.$this->line;
        }

        if (false === $depth) {
            $this->lastDepth = -1;
            $this->line .= $this->dumpSuffix;
        } else {
            $this->lastDepth = $depth;
        }

        parent::dumpLine($depth);
    }
}
