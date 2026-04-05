<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Tests\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Smartlabs\SonataTranslationListBundle\Action\SaveTranslationAction;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\Pool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SaveTranslationActionTest extends TestCase
{
    private ContainerInterface&MockObject $container;
    private EntityManagerInterface&MockObject $entityManager;
    private CsrfTokenManagerInterface&MockObject $csrfTokenManager;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
    }

    private function createAction(array $adminServiceCodes = [], array $locales = ['de', 'en']): SaveTranslationAction
    {
        $pool = new Pool($this->container, $adminServiceCodes);

        return new SaveTranslationAction(
            $pool,
            $this->entityManager,
            $this->csrfTokenManager,
            $locales,
        );
    }

    private function createJsonRequest(array $data, string $method = 'POST', string $csrfToken = 'valid-token'): Request
    {
        $request = Request::create('/save', $method, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($data, JSON_THROW_ON_ERROR));

        $request->headers->set('X-CSRF-Token', $csrfToken);

        return $request;
    }

    private function setupValidCsrf(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);
    }

    private function setupInvalidCsrf(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'adminCode' => 'test_admin',
            'objectId' => '1',
            'locale' => 'en',
            'field' => 'title',
            'value' => 'Test Value',
        ], $overrides);
    }

    // --- Tests ---

    public function testMethodNotAllowed(): void
    {
        $action = $this->createAction();
        $request = Request::create('/save', 'GET');

        $response = $action($request);

        self::assertSame(405, $response->getStatusCode());
    }

    public function testInvalidCsrfToken(): void
    {
        $this->setupInvalidCsrf();
        $action = $this->createAction();

        $response = $action($this->createJsonRequest($this->validPayload()));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('CSRF', json_decode($response->getContent(), true)['message']);
    }

    public function testInvalidJsonBody(): void
    {
        $this->setupValidCsrf();
        $action = $this->createAction();

        $request = Request::create('/save', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{broken json');
        $request->headers->set('X-CSRF-Token', 'valid-token');

        $response = $action($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Invalid JSON', json_decode($response->getContent(), true)['message']);
    }

    public function testMissingParameters(): void
    {
        $this->setupValidCsrf();
        $action = $this->createAction();

        $response = $action($this->createJsonRequest(['adminCode' => 'test']));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Missing', json_decode($response->getContent(), true)['message']);
    }

    public function testInvalidLocale(): void
    {
        $this->setupValidCsrf();
        $action = $this->createAction();

        $response = $action($this->createJsonRequest($this->validPayload(['locale' => 'xx'])));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Invalid locale', json_decode($response->getContent(), true)['message']);
    }

    public function testInvalidAdminCode(): void
    {
        $this->setupValidCsrf();
        // No admin registered → getAdminByAdminCode throws
        $action = $this->createAction();

        $response = $action($this->createJsonRequest($this->validPayload()));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testObjectNotFound(): void
    {
        $this->setupValidCsrf();

        $admin = $this->createMock(AdminInterface::class);
        $admin->method('getObject')->willReturn(null);
        $this->container->method('get')->with('test_admin')->willReturn($admin);

        $action = $this->createAction(['test_admin']);

        $response = $action($this->createJsonRequest($this->validPayload()));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAccessDenied(): void
    {
        $this->setupValidCsrf();

        $object = $this->createMock(TranslatableInterface::class);

        $admin = $this->createMock(AdminInterface::class);
        $admin->method('getObject')->willReturn($object);
        $admin->method('hasAccess')->with('edit', $object)->willReturn(false);
        $this->container->method('get')->with('test_admin')->willReturn($admin);

        $action = $this->createAction(['test_admin']);

        $response = $action($this->createJsonRequest($this->validPayload()));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Access denied', json_decode($response->getContent(), true)['message']);
    }

    public function testNonTranslatableEntity(): void
    {
        $this->setupValidCsrf();

        $object = new \stdClass();

        $admin = $this->createMock(AdminInterface::class);
        $admin->method('getObject')->willReturn($object);
        $admin->method('hasAccess')->willReturn(true);
        $this->container->method('get')->with('test_admin')->willReturn($admin);

        $action = $this->createAction(['test_admin']);

        $response = $action($this->createJsonRequest($this->validPayload()));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('not translatable', json_decode($response->getContent(), true)['message']);
    }
}
