<?php

declare(strict_types=1);

use Smartlabs\SonataTranslationListBundle\Action\SaveTranslationAction;
use Smartlabs\SonataTranslationListBundle\Admin\Extension\TranslationListAdminExtension;
use Smartlabs\SonataTranslationListBundle\Twig\TranslationListExtension;
use Smartlabs\SonataTranslationListBundle\Twig\TranslationListRuntime;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(TranslationListAdminExtension::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sonata_translation_list.locales'),
            param('sonata_translation_list.default_locale'),
        ]);

    $services->set(SaveTranslationAction::class)
        ->public()
        ->tag('controller.service_arguments')
        ->args([
            service('sonata.admin.pool'),
            service('doctrine.orm.entity_manager'),
            service('security.csrf.token_manager'),
        ]);

    // Twig extension (registers the translation_list_config function)
    $services->set(TranslationListExtension::class)
        ->tag('twig.extension');

    // Twig runtime (lazy-loaded, provides the actual implementation)
    $services->set(TranslationListRuntime::class)
        ->tag('twig.runtime')
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sonata_translation_list.locales'),
            param('sonata_translation_list.default_locale'),
        ]);
};
