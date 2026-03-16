<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslationListExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('translation_list_config', [TranslationListRuntime::class, 'getConfig']),
        ];
    }
}
