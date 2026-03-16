<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Twig\Extension\RuntimeExtensionInterface;

class TranslationListRuntime implements RuntimeExtensionInterface
{
    /** @var array<string, array<string, string>> */
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
     * @return array{fields: array<string, string>, locales: string[], defaultLocale: string}
     */
    public function getConfig(object $admin): array
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
     * @return array<string, string> Field name => human-readable label
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

            // Convert camelCase to human-readable label
            $label = ucfirst(trim(preg_replace('/[A-Z]/', ' $0', $fieldName)));
            $fields[$fieldName] = $label;
        }

        $this->fieldCache[$modelClass] = $fields;

        return $fields;
    }
}
