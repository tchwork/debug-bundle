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
                ->integerNode('max_length')
                    ->min(0)
                    ->defaultValue(1000)
                    ->info('Max number of dumped elements, all levels included, 0 means no limit')
                ->end()
                ->integerNode('max_depth')
                    ->min(0)
                    ->defaultValue(10)
                    ->info('Max number of dumper levels for arrays/objects, 0 means no limit')
                ->end()
                ->integerNode('max_string')
                    ->min(0)
                    ->defaultValue(100000)
                    ->info('Max length of dumped strings, 0 means no limit')
                ->end()
                ->integerNode('max_string_width')
                    ->min(0)
                    ->defaultValue(120)
                    ->info('Max length per line of dumped strings, 0 means no limit')
                ->end()
                ->booleanNode('check_internal_refs')
                    ->defaultValue(true)
                    ->info('Enable/disable detecting non-recursive internal references into arrays/objects')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
