<?php

namespace Patchwork\DumperBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class PatchworkDumperExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $container->getDefinition('patchwork.dumper.json')
            ->setProperty('maxItems',  $config['max_items'])
            ->setProperty('maxString', $config['max_string']);

        $container->getDefinition('patchwork.dumper.html')
            ->setProperty('maxItems',  $config['max_items'])
            ->setProperty('maxString', $config['max_string']);

        $container->getDefinition('patchwork.dumper.cli')
            ->setProperty('maxItems',  $config['max_items'])
            ->setProperty('maxString', $config['max_string']);
    }
}
