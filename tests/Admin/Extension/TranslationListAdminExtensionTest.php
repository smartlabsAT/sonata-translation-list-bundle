<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Tests\Admin\Extension;

use PHPUnit\Framework\TestCase;
use Smartlabs\SonataTranslationListBundle\Admin\Extension\TranslationListAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;

class TranslationListAdminExtensionTest extends TestCase
{
    private TranslationListAdminExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new TranslationListAdminExtension(
            ['de', 'en', 'fr'],
            'de',
        );
    }

    public function testConfigure(): void
    {
        $admin = $this->createMock(AdminInterface::class);

        $admin->method('getListModes')->willReturn([
            'list' => ['class' => 'fas fa-list fa-fw'],
        ]);

        $admin->expects(self::once())
            ->method('setListModes')
            ->with(self::callback(function (array $modes): bool {
                return isset($modes['translation'])
                    && str_contains($modes['translation']['icon'], 'fa-language');
            }));

        $admin->expects(self::once())
            ->method('setTemplate')
            ->with('outer_list_rows_translation', '@SonataTranslationList/list_outer_rows_translation.html.twig');

        $this->extension->configure($admin);
    }

    public function testConfigurePersistentParametersWithTranslationFields(): void
    {
        $admin = $this->createMock(AdminInterface::class);
        $request = new \Symfony\Component\HttpFoundation\Request(['translation_fields' => 'title,description']);

        $admin->method('hasRequest')->willReturn(true);
        $admin->method('getRequest')->willReturn($request);

        $result = $this->extension->configurePersistentParameters($admin, []);

        self::assertSame('title,description', $result['translation_fields']);
    }

    public function testConfigurePersistentParametersWithLegacyParam(): void
    {
        $admin = $this->createMock(AdminInterface::class);
        $request = new \Symfony\Component\HttpFoundation\Request(['translation_field' => 'title']);

        $admin->method('hasRequest')->willReturn(true);
        $admin->method('getRequest')->willReturn($request);

        $result = $this->extension->configurePersistentParameters($admin, []);

        self::assertSame('title', $result['translation_fields']);
    }

    public function testConfigurePersistentParametersWithoutRequest(): void
    {
        $admin = $this->createMock(AdminInterface::class);
        $admin->method('hasRequest')->willReturn(false);

        $result = $this->extension->configurePersistentParameters($admin, ['existing' => 'value']);

        self::assertSame(['existing' => 'value'], $result);
    }

    public function testGetLocales(): void
    {
        self::assertSame(['de', 'en', 'fr'], $this->extension->getLocales());
    }

    public function testGetDefaultLocale(): void
    {
        self::assertSame('de', $this->extension->getDefaultLocale());
    }
}
