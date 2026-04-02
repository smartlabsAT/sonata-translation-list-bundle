<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SonataTranslationListBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('locales')
                    ->defaultValue(['de', 'en'])
                    ->scalarPrototype()->end()
                    ->info('List of locales available for translation.')
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('%kernel.default_locale%')
                    ->info('The default (source) locale.')
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('sonata_translation_list.locales', $config['locales']);
        $builder->setParameter('sonata_translation_list.default_locale', $config['default_locale']);

        $container->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('sonata_admin')) {
            $builder->prependExtensionConfig('sonata_admin', [
                'assets' => [
                    'extra_javascripts' => [
                        'bundles/sonatatranslationlist/js/translation-list.js',
                    ],
                    'extra_stylesheets' => [
                        'bundles/sonatatranslationlist/css/translation-list.css',
                    ],
                ],
            ]);
        }
    }
}
