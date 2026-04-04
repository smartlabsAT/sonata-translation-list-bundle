<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Twig\Extension\RuntimeExtensionInterface;

class TranslationListRuntime implements RuntimeExtensionInterface
{
    /** @var array<string, array<string, array{label: string, type: string}>> */
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

    /**
     * Returns translation list configuration for the given admin.
     *
     * @return array{fields: array<string, array{label: string, type: string}>, locales: string[], defaultLocale: string}
     */
    public function getConfig(AdminInterface $admin): array
    {
        $modelClass = $admin->getModelClass();

        return [
            'fields' => $this->discoverFields($modelClass),
            'locales' => $this->locales,
            'defaultLocale' => $this->defaultLocale,
        ];
    }

    /**
     * Discovers translatable fields from the Translation entity's Doctrine metadata.
     *
     * @return array<string, array{label: string, type: string}>
     */
    private function discoverFields(string $modelClass): array
    {
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
            if (in_array($fieldName, ['id', 'locale'], true)) {
                continue;
            }

            $fieldMapping = $metadata->getFieldMapping($fieldName);
            $type = $fieldMapping->type ?? 'string';

            $label = ucfirst(trim(preg_replace('/[A-Z]/', ' $0', $fieldName) ?? $fieldName));
            $fields[$fieldName] = [
                'label' => $label,
                'type' => $type,
            ];
        }

        $this->fieldCache[$modelClass] = $fields;

        return $fields;
    }
}
