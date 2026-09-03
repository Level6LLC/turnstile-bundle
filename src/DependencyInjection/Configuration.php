<?php

namespace Level6\TurnstileBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('turnstile');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('sitekey')
                    ->defaultNull()
                    ->info('Public Turnstile sitekey rendered into the widget div.')
                ->end()
                ->scalarNode('secret')
                    ->defaultNull()
                    ->info('Turnstile secret used for the server-side siteverify call. An empty value disables verification for that environment.')
                ->end()
                ->arrayNode('hostnames')
                    ->scalarPrototype()->end()
                    ->info('Optional allowlist of frontend hostnames checked against the siteverify response. Empty means the hostname is not checked.')
                ->end()
                ->arrayNode('surfaces')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('route')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Route name the guard matches (e.g. "login").')
                            ->end()
                            ->arrayNode('methods')
                                ->scalarPrototype()->end()
                                ->defaultValue(['POST'])
                            ->end()
                            ->scalarNode('action')
                                ->defaultNull()
                                ->info('Turnstile action sent by the widget; defaults to the surface key.')
                            ->end()
                            ->enumNode('on_failure')
                                ->values(['flash_redirect', '403'])
                                ->defaultValue('flash_redirect')
                            ->end()
                            ->scalarNode('flash_type')
                                ->defaultValue('danger')
                                ->info('Session flashbag type used when on_failure is flash_redirect.')
                            ->end()
                            ->scalarNode('message')
                                ->defaultValue('Security verification failed. Please try again.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}