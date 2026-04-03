<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Admin\Extension;

use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;

class TranslationListAdminExtension extends AbstractAdminExtension
{
    /**
     * @param string[] $locales
     */
    public function __construct(
        private readonly array $locales,
        private readonly string $defaultLocale,
    ) {
    }

    public function configure(AdminInterface $admin): void
    {
        $listModes = $admin->getListModes();

        $listModes['translation'] = [
            'icon' => '<i class="fas fa-language fa-fw" aria-hidden="true"></i>',
            'class' => 'fas fa-language fa-fw',
        ];

        $admin->setListModes($listModes);

        $admin->setTemplate(
            'outer_list_rows_translation',
            '@SonataTranslationList/list_outer_rows_translation.html.twig'
        );
    }

    public function configurePersistentParameters(AdminInterface $admin, array $parameters): array
    {
        if ($admin->hasRequest()) {
            $request = $admin->getRequest();
            if ($request->query->has('translation_fields')) {
                $parameters['translation_fields'] = $request->query->get('translation_fields');
            } elseif ($request->query->has('translation_field')) {
                // backwards compatibility with single-field param
                $parameters['translation_fields'] = $request->query->get('translation_field');
            }
        }

        return $parameters;
    }

    /**
     * @return string[]
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
