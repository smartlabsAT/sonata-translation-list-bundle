<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle;

use Smartlabs\SonataTranslationListBundle\DependencyInjection\SonataTranslationListExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SonataTranslationListBundle extends Bundle
{
    protected function getContainerExtensionClass(): string
    {
        return SonataTranslationListExtension::class;
    }
}
