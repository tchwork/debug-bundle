<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patchwork\DumperBundle\Twig;

/**
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
class DebugNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $values = null, $lineno, $tag = null)
    {
        parent::__construct(array('values' => $values), array(), $lineno, $tag);
    }

    /**
     * {@inheritdoc}
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $compiler
            ->write("if (\$this->env->isDebug()) {\n")
            ->indent()
        ;

        $values = $this->getNode('values');

        $compiler->write('\Patchwork\Dumper\VarDebug::debug(');
        if (null === $values) {
            $compiler->raw('$context');
        } elseif ($values->count() === 1) {
            $compiler->subcompile($values->getNode(0));
        } else {
            $compiler->raw('array(');
            foreach ($values as $node) {
                if ($node->hasAttribute('name')) {
                    $compiler
                        ->string($node->getAttribute('name'))
                        ->raw('=>')
                    ;
                }
                $compiler
                    ->subcompile($node)
                    ->raw(',')
                ;
            }
            $compiler->raw(')');
        }
        $compiler->raw(");\n");

        $compiler
            ->outdent()
            ->write("}\n")
        ;
    }
}
