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
    protected $env;

    public function __construct(\Twig_Environment $env, \Twig_NodeInterface $values = null, $lineno = 0, $tag = null)
    {
        parent::__construct(array('values' => $values), array(), $lineno, $tag);
        $this->env = $env;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(\Twig_Compiler $compiler)
    {
        if (!$this->env->isDebug()) {
            return;
        }

        $compiler->addDebugInfo($this);
        $compiler->write('debug(');
        if (!$values = $this->getNode('values')) {
            $compiler->raw('$context');
        } elseif ($values->count() === 1) {
            $compiler->subcompile($values->getNode(0));
        } else {
            $compiler->raw('array(');
            foreach ($values as $node) {
                if ($node->hasAttribute('name')) {
                    $compiler->raw("'".addslashes($node->getAttribute('name'))."' => ");
                }
                $compiler
                    ->subcompile($node)
                    ->raw(',')
                ;
            }
            $compiler->raw(')');
        }
        $compiler->raw(");\n");
    }
}
