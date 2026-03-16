<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Admin\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;

class TranslationListAdminExtension extends AbstractAdminExtension
{
    /** @var array<string, array<string, string>> Cache of discovered fields per model class */
    private array $fieldCache = [];

    /**
     * @param string[] $locales
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
     * Returns the translatable fields discovered from the Translation entity.
     *
     * @return array<string, string> Field name => human-readable label
     */
    public function getTranslatableFields(AdminInterface $admin): array
    {
        $modelClass = $admin->getModelClass();

        if (isset($this->fieldCache[$modelClass])) {
            return $this->fieldCache[$modelClass];
        }

        if (!is_subclass_of($modelClass, TranslatableInterface::class)) {
            $this->fieldCache[$modelClass] = [];
            return [];
        }

        $translationClass = $modelClass::getTranslationEntityClass();
        $metadata = $this->entityManager->getClassMetadata($translationClass);
        $fields = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            // Skip internal fields
            if (in_array($fieldName, ['id', 'locale'], true)) {
                continue;
            }

            // Convert camelCase to human-readable label
            $label = ucfirst(trim(preg_replace('/[A-Z]/', ' $0', $fieldName)));

            $fields[$fieldName] = $label;
        }

        $this->fieldCache[$modelClass] = $fields;

        return $fields;
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
