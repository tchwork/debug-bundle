<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper;

/**
 * HtmlDumper dumps variable as HTML.
 */
class HtmlDumper extends CliDumper
{
    public

    $colors = true,
    $dumpPrefix,
    $dumpSuffix,
    $maxStringWidth = 240;

    public static

    $defaultOutputStream = 'php://output';

    protected

    $lastDepth = -1,
    $styles = array(
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


    public function __construct($outputStream = null, array $defaultCasters = null)
    {
        parent::__construct($outputStream, $defaultCasters);

        $this->setStyles($this->styles);
    }

    public function setStyles(array $styles)
    {
        $this->styles = $styles + $this->styles;

        $s = '';
        $p = 'patchwork-dumper';

        foreach ($styles as $class => $style)
            $s .= ".$p-$class{{$style}}";

        $this->dumpPrefix = "<style>$s</style><pre class=$p>";
        $this->dumpSuffix = '</pre>';
    }

    protected function style($style, $val)
    {
        $val = htmlspecialchars($val, ENT_NOQUOTES, 'UTF-8');

        switch ($style)
        {
        case 'str':
        case 'public':
            static $cchr = array(
                "\x1B",
                "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
                "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
                "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
                "\x18", "\x19", "\x1A", "\x1C", "\x1D", "\x1E", "\x1F", "\x7F",
            );

            foreach ($cchr as $c)
            {
                if (false !== strpos($val, $c))
                {
                    $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                    $val = str_replace($c, "<span class=patchwork-dumper-cchr>$r</span>", $val);
                }
            }
        }

        return "<span class=patchwork-dumper-$style>$val</span>";
    }

    protected function dumpLine($depthOffset)
    {
        $depth = $this->depth + $depthOffset;

        switch ($this->lastDepth - $depth)
        {
        case +1: $this->line = '</span>' . $this->line; break;
        case -1: $this->line = "<span class=patchwork-dumper-$depth>$this->line"; break;
        }

        if (-1 === $this->lastDepth) $this->line = $this->dumpPrefix . $this->line;

        if (false === $depthOffset)
        {
            $this->lastDepth = -1;
            $this->line .= $this->dumpSuffix;
        }
        else $this->lastDepth = $depth;

        parent::dumpLine($depthOffset);
    }
}
