<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Tests;

use PHPUnit\Framework\TestCase;
use Smartlabs\SonataTranslationListBundle\SonataTranslationListBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class SmartlabsSonataTranslationListBundleTest extends TestCase
{
    private function createContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.debug' => true,
        ]));
    }

    public function testLoadExtensionRegistersServices(): void
    {
        $bundle = new SonataTranslationListBundle();
        $container = $this->createContainerBuilder();

        $extension = $bundle->getContainerExtension();
        $extension->load([['locales' => ['en', 'de'], 'default_locale' => 'en', 'ckeditor_paths' => []]], $container);

        self::assertTrue($container->hasParameter('sonata_translation_list.locales'));
        self::assertSame(['en', 'de'], $container->getParameter('sonata_translation_list.locales'));
        self::assertSame('en', $container->getParameter('sonata_translation_list.default_locale'));
        self::assertSame([], $container->getParameter('sonata_translation_list.ckeditor_paths'));
    }

    public function testPrependExtensionRegistersSonataAdminAssets(): void
    {
        $bundle = new SonataTranslationListBundle();
        $container = $this->createContainerBuilder();

        $sonataAdminExtension = $this->createMock(ExtensionInterface::class);
        $sonataAdminExtension->method('getAlias')->willReturn('sonata_admin');
        $container->registerExtension($sonataAdminExtension);

        $extension = $bundle->getContainerExtension();
        $extension->prepend($container);

        $configs = $container->getExtensionConfig('sonata_admin');

        self::assertNotEmpty($configs);
        self::assertArrayHasKey('assets', $configs[0]);
        self::assertContains(
            'bundles/sonatatranslationlist/js/translation-list.js',
            $configs[0]['assets']['extra_javascripts']
        );
        self::assertContains(
            'bundles/sonatatranslationlist/css/translation-list.css',
            $configs[0]['assets']['extra_stylesheets']
        );
    }

    public function testPrependExtensionSkipsWhenSonataAdminAbsent(): void
    {
        $bundle = new SonataTranslationListBundle();
        $container = $this->createContainerBuilder();

        $extension = $bundle->getContainerExtension();
        $extension->prepend($container);

        self::assertEmpty($container->getExtensionConfig('sonata_admin'));
    }
}
