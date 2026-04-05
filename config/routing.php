<?php

declare(strict_types=1);

use Smartlabs\SonataTranslationListBundle\Action\SaveTranslationAction;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('sonata_translation_list_save', '/translation-list/save')
        ->controller(SaveTranslationAction::class)
        ->methods(['POST']);
};
