<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * DebugExtension configuration structure.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('debug');

        $rootNode
            ->children()
                ->integerNode('max_items')
                    ->info('Max number of displayed items, all levels included, 0 means no limit, -1 only first level')
                    ->min(-1)
                    ->defaultValue(1000)
                ->end()
                ->integerNode('max_string_length')
                    ->info('Max length of displayed strings, 0 means no limit')
                    ->min(0)
                    ->defaultValue(10000)
                ->end()
                ->scalarNode('dump_path')
                    ->info('Where dumps are written to, leave empty to put them in the toolbar')
                    ->defaultValue('')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
