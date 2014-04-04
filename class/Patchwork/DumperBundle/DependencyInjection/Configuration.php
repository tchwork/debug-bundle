<?php

namespace Patchwork\DumperBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('patchwork_dumper');

        $rootNode
            ->children()
                ->integerNode('max_items')
                    ->min(0)
                    ->defaultValue(500)
                    ->info('Max number of dumped elements, all levels included, 0 means no limit, -1 only first level')
                ->end()
                ->integerNode('max_string')
                    ->min(0)
                    ->defaultValue(5000)
                    ->info('Max length of dumped strings, 0 means no limit')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
