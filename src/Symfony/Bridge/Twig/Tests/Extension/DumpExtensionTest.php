<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\DumpExtension;
use Symfony\Component\VarDumper\VarDumper;
use Symfony\Component\VarDumper\Cloner\PhpCloner;

class DumpExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDumpTags
     */
    public function testDumpTag($template, $debug, $expectedOutput, $expectedDumped)
    {
        $extension = new DumpExtension(new PhpCloner());
        $twig = new \Twig_Environment(new \Twig_Loader_String(), array(
            'debug' => $debug,
            'cache' => false,
            'optimizations' => 0,
        ));
        $twig->addExtension($extension);

        $dumped = null;
        $exception = null;
        $prevDumper = VarDumper::setHandler(function ($var) use (&$dumped) {$dumped = $var;});

        try {
            $this->assertEquals($expectedOutput, $twig->render($template));
        } catch (\Exception $exception) {
        }

        VarDumper::setHandler($prevDumper);

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($expectedDumped, $dumped);
    }

    public function getDumpTags()
    {
        return array(
            array('A{% dump %}B', true, 'AB', array()),
            array('A{% set foo="bar"%}B{% dump %}C', true, 'ABC', array('foo' => 'bar')),
            array('A{% dump %}B', false, 'AB', null),
        );
    }

    /**
     * @dataProvider getDumpArgs
     */
    public function testDump($context, $args, $expectedOutput, $debug = true)
    {
        $extension = new DumpExtension(new PhpCloner());
        $twig = new \Twig_Environment(new \Twig_Loader_String(), array(
            'debug' => $debug,
            'cache' => false,
            'optimizations' => 0,
        ));

        array_unshift($args, $context);
        array_unshift($args, $twig);

        $dump = call_user_func_array(array($extension, 'dump'), $args);

        if ($debug) {
            $this->assertStringStartsWith('<style> pre.sf-dump { background-color: #300a24; white-space: pre; line-height: 1.2em; color: #eee8d5; font-family: monospace, sans-serif; padding: 5px; } .sf-dump span { display: inline; }a.sf-dump-ref {color:#444444}span.sf-dump-num {font-weight:bold;color:#0087FF}span.sf-dump-const {font-weight:bold;color:#0087FF}span.sf-dump-str {font-weight:bold;color:#00D7FF}span.sf-dump-cchr {font-style: italic}span.sf-dump-note {color:#D7AF00}span.sf-dump-ref {color:#444444}span.sf-dump-public {color:#008700}span.sf-dump-protected {color:#D75F00}span.sf-dump-private {color:#D70000}span.sf-dump-meta {color:#005FFF}</style>', $dump);
            $dump = substr($dump, 620);
        }
        $this->assertEquals($expectedOutput, $dump);
    }

    public function getDumpArgs()
    {
        return array(
            array(array(), array(), '', false),
            array(array(), array(), "<pre class=sf-dump><span class=sf-dump-0>[]\n</span></pre>\n"),
            array(
                array(),
                array(123, 456),
                "<pre class=sf-dump><span class=sf-dump-0><span class=sf-dump-num>123</span>\n</span></pre>\n"
                ."<pre class=sf-dump><span class=sf-dump-0><span class=sf-dump-num>456</span>\n</span></pre>\n",
            ),
            array(
                array('foo' => 'bar'),
                array(),
                "<pre class=sf-dump><span class=sf-dump-0><span class=sf-dump-note>array:1</span> [\n"
                ."  <span class=sf-dump-1>\"<span class=sf-dump-meta>foo</span>\" => \"<span class=sf-dump-str>bar</span>\"\n"
                ."</span>]\n"
                ."</span></pre>\n",
            ),
        );
    }
}
