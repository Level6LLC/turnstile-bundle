<?php

namespace Level6\TurnstileBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('turnstile');

        // getRootNode() was introduced in Symfony 4.2; 4.0/4.1 return the root
        // node from TreeBuilder::root() instead.
        $rootNode = method_exists($treeBuilder, 'getRootNode')
            ? $treeBuilder->getRootNode()
            : $treeBuilder->root('turnstile');

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
                            ->scalarNode('on_failure')
                                ->defaultValue('flash_redirect')
                                ->validate()
                                    ->ifNotInArray(['flash_redirect', '403'])
                                    ->thenInvalid('Invalid on_failure value "%s"; expected "flash_redirect" or "403".')
                                ->end()
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