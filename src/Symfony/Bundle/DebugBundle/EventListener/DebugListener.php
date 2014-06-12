<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\EventListener;

use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures debug() handler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugListener implements EventSubscriberInterface
{
    private $container;
    private $dumper;

    /**
     * @param ContainerInterface $container
     * @param string             $dumper var_dumper dumper service that is used
     */
    public function __construct(ContainerInterface $container, $dumper)
    {
        $this->container = $container;
        $this->dumper = $dumper;
    }

    public function configure()
    {
        if ($this->container) {
            $container = $this->container;
            $dumper = $this->dumper;
            $this->container = null;

            DebugBundle::setHandler(function ($var) use ($container, $dumper) {
                $dumper = $container->get($dumper);
                $cloner = $container->get('var_dumper.cloner');
                $handler = function ($var) use ($dumper, $cloner) {$dumper->dump($cloner->cloneVar($var));};
                DebugBundle::setHandler($handler);
                $handler($var);
            });
        }
    }

    public static function getSubscribedEvents()
    {
        // Register early to have a working debug() as early as possible
        return array(KernelEvents::REQUEST => array('configure', 1024));
    }
}
