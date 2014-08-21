<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\Twig;

use Symfony\Bundle\DebugBundle\DebugBundle;

/**
 * Provides integration of the debug() function with Twig.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugExtension extends \Twig_Extension
{
    public function getTokenParsers()
    {
        return array(new TokenParser\DebugTokenParser());
    }

    public function getName()
    {
        return 'symfony_debug';
    }
}
