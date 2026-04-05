<?php

declare(strict_types=1);

namespace Smartlabs\SonataTranslationListBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Smartlabs\SonataTranslationListBundle\SonataTranslationListBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;

class ConfigurationTest extends TestCase
{
    private function processConfig(array $config): array
    {
        $bundle = new SonataTranslationListBundle();

        $treeBuilder = new TreeBuilder('sonata_translation_list');
        $loader = new DefinitionFileLoader($treeBuilder, new FileLocator());
        $definition = new DefinitionConfigurator($treeBuilder, $loader, __DIR__, __FILE__);

        $bundle->configure($definition);

        $tree = $treeBuilder->buildTree();

        return (new Processor())->process($tree, [$config]);
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processConfig(['locales' => ['en']]);

        self::assertSame('%kernel.default_locale%', $config['default_locale']);
        self::assertSame([], $config['ckeditor_paths']);
    }

    public function testLocalesRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([]);
    }

    public function testLocalesRequiresAtLeastOneElement(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig(['locales' => []]);
    }

    public function testCustomConfiguration(): void
    {
        $config = $this->processConfig([
            'locales' => ['en', 'fr', 'es'],
            'default_locale' => 'en',
        ]);

        self::assertSame(['en', 'fr', 'es'], $config['locales']);
        self::assertSame('en', $config['default_locale']);
    }

    public function testCkeditorPathsDefault(): void
    {
        $config = $this->processConfig(['locales' => ['en']]);

        self::assertSame([], $config['ckeditor_paths']);
    }

    public function testCkeditorPathsCustom(): void
    {
        $config = $this->processConfig([
            'locales' => ['en'],
            'ckeditor_paths' => ['/build/ckeditor/ckeditor.js'],
        ]);

        self::assertSame(['/build/ckeditor/ckeditor.js'], $config['ckeditor_paths']);
    }
}
