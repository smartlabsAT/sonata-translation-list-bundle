<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sonata_translation_list');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('locales')
                    ->defaultValue(['de', 'en'])
                    ->scalarPrototype()->end()
                    ->info('List of locales available for translation.')
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('%kernel.default_locale%')
                    ->info('The default (source) locale. Columns for this locale are read-only.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
