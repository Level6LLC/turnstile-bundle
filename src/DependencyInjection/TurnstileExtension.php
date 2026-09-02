<?php

namespace Level6\TurnstileBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class TurnstileExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('turnstile.sitekey', null !== $config['sitekey'] ? (string) $config['sitekey'] : '');
        $container->setParameter('turnstile.secret', null !== $config['secret'] ? (string) $config['secret'] : '');
        $container->setParameter('turnstile.hostnames', $config['hostnames']);
        $container->setParameter('turnstile.surfaces', $config['surfaces']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }

    public function getAlias(): string
    {
        return 'turnstile';
    }
}