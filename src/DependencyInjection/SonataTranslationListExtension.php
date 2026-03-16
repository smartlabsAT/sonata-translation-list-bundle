<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SonataTranslationListExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sonata_admin')) {
            $container->prependExtensionConfig('sonata_admin', [
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

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('sonata_translation_list.locales', $config['locales']);
        $container->setParameter('sonata_translation_list.default_locale', $config['default_locale']);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.php');
    }
}
