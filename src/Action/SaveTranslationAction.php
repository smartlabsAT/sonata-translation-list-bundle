<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SaveTranslationAction
{
    private const CSRF_TOKEN_ID = 'translation_list_save';

    private const EXCLUDED_FIELDS = ['id', 'locale'];

    public function __construct(
        private readonly Pool $adminPool,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        // CSRF validation
        $token = $request->headers->get('X-CSRF-Token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $adminCode = $data['adminCode'] ?? null;
        $objectId = $data['objectId'] ?? null;
        $locale = $data['locale'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? '';

        if (!$adminCode || !$objectId || !$locale || !$field) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing required parameters'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $admin = $this->adminPool->getAdminByAdminCode($adminCode);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid admin code'], Response::HTTP_BAD_REQUEST);
        }

        // Permission check
        if (!$admin->hasAccess('edit')) {
            return new JsonResponse(['status' => 'error', 'message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $object = $admin->getObject($objectId);

        if (null === $object) {
            return new JsonResponse(['status' => 'error', 'message' => 'Object not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$object instanceof TranslatableInterface) {
            return new JsonResponse(['status' => 'error', 'message' => 'Entity is not translatable'], Response::HTTP_BAD_REQUEST);
        }

        // Validate field against Translation entity metadata
        $translationClass = $object::getTranslationEntityClass();
        $metadata = $this->entityManager->getClassMetadata($translationClass);
        $allowedFields = array_diff($metadata->getFieldNames(), self::EXCLUDED_FIELDS);

        if (!in_array($field, $allowedFields, true)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid field name'], Response::HTTP_BAD_REQUEST);
        }

        // Set translation value
        $setter = 'set' . ucfirst($field);
        $translation = $object->translate($locale, false);

        if (!method_exists($translation, $setter)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Setter not found on translation entity'], Response::HTTP_BAD_REQUEST);
        }

        $translation->$setter($value);
        $object->mergeNewTranslations();

        $this->entityManager->persist($object);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'ok', 'value' => $value]);
    }
}
