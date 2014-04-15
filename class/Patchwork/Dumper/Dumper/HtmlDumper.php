<?php

namespace Patchwork\Dumper\Dumper;

/**
 * HtmlDumper dumps variable as HTML.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HtmlDumper extends CliDumper
{
    public static $defaultOutputStream = 'php://output';

    protected $dumpHeader = '';
    protected $dumpPrefix = '<pre class=sf-var-debug style=white-space:pre>';
    protected $dumpSuffix = '</pre>';
    protected $colors = true;
    protected $headerIsDumped = false;
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

        if (!isset($this->dumpHeader)) {
            $this->setStyles($this->styles);
        }
    }

    public function setLineDumper($callback)
    {
        $this->headerIsDumped = false;

        return parent::setLineDumper($callback);
    }

    public function setStyles(array $styles)
    {
        $this->headerIsDumped = false;
        $this->styles = $styles + $this->styles;
    }

    public function setDumpHeader($header)
    {
        $this->dumpHeader = $header;
    }

    public function setDumpBoudaries($prefix, $suffix)
    {
        $this->dumpPrefix = $prefix;
        $this->dumpSuffix = $suffix;
    }

    public function dumpHeader()
    {
        $this->headerIsDumped = true;

        $p = 'sf-var-debug';
        parent::dumpLine('<style>');
        parent::dumpLine("a.$p-ref {{$this->styles['ref']}}");

        foreach ($this->styles as $class => $style) {
            parent::dumpLine("span.$p-$class {{$style}}");
        }

        parent::dumpLine('</style>');
        parent::dumpLine($this->dumpHeader);
    }

    public function dumpStart()
    {
        if (!$this->headerIsDumped) {
            $this->dumpHeader();
        }
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

        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

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
