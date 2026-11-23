<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Maker;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use SymPress\MakerBundle\Maker\MakeAssetEntry;
use SymPress\MakerBundle\Maker\MakeBlock;
use SymPress\MakerBundle\Maker\MakeDataProvider;
use SymPress\MakerBundle\Maker\MakeSympressPackage;
use SymPress\MakerBundle\Tests\Fixtures\ArrayComposerPackageLocator;
use SymPress\MakerBundle\Util\JsonFile;
use SymPress\MakerBundle\Util\PackageAwareAutoloaderUtil;
use SymPress\MakerBundle\Util\PackageAwareGenerator;
use SymPress\MakerBundle\Util\PackageContextResolver;
use SymPress\MakerBundle\Util\PackageServiceConfigurator;
use SymPress\MakerBundle\Util\WebpackEncoreEntryConfigurator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Bundle\MakerBundle\Util\TemplateComponentGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

final class MakerInputSafetyTest extends TestCase
{
    private string $workspace;
    private PackageAutoloadResolver $autoloadResolver;
    private PackageContextResolver $contextResolver;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-maker-input-' . bin2hex(random_bytes(6));
        mkdir($this->workspace . '/packages/plugin', 0777, true);
        file_put_contents(
            $this->workspace . '/packages/plugin/composer.json',
            json_encode(
                [
                    'name'     => 'site/plugin',
                    'type'     => 'wordpress-plugin',
                    'autoload' => ['psr-4' => ['Site\\Plugin\\' => 'src/']],
                ],
                \JSON_THROW_ON_ERROR,
            ),
        );
        $locator = new ArrayComposerPackageLocator(['site/plugin' => $this->workspace . '/packages/plugin']);
        $this->autoloadResolver = new PackageAutoloadResolver($locator);
        $this->contextResolver = new PackageContextResolver($this->workspace, $locator, $this->autoloadResolver);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);
    }

    public function testDataProviderRejectsMethodNameThatIsNotAnIdentifier(): void
    {
        $maker = new MakeDataProvider($this->contextResolver, new PackageServiceConfigurator());

        try {
            $this->make($maker, [
                'class'        => 'Site\\Plugin\\DataProvider\\Templating\\EvilDataProvider',
                '--method'     => "provide(): array { return []; }\n    public function __construct() { system('id'); }\n    public function x",
                '--no-service' => true,
            ]);
            self::fail('Invalid method name accepted.');
        } catch (\InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($this->workspace . '/packages/plugin/src');
        }
    }

    public function testAssetEntryRejectsSourcePathOutsideThePackage(): void
    {
        mkdir($this->workspace . '/packages/plugin/Resources');

        try {
            $this->make($this->assetEntryMaker(), [
                'package'     => 'site/plugin',
                'name'        => 'frontend',
                '--path'      => 'Resources/../../../escape.ts',
                '--no-loader' => true,
            ]);
            self::fail('Traversal path accepted.');
        } catch (\InvalidArgumentException) {
            self::assertFileDoesNotExist($this->workspace . '/escape.ts');
            self::assertFileDoesNotExist($this->workspace . '/packages/plugin/webpack.config.js');
        }
    }

    public function testAssetEntryStripsOnlyALeadingDotSlashFromThePath(): void
    {
        $this->make($this->assetEntryMaker(), [
            'package'     => 'site/plugin',
            'name'        => 'frontend',
            '--path'      => './.config/entry.ts',
            '--no-loader' => true,
        ]);
        self::assertFileExists($this->workspace . '/packages/plugin/.config/entry.ts');
        self::assertDirectoryDoesNotExist($this->workspace . '/packages/plugin/config');

        $this->expectException(\InvalidArgumentException::class);
        $this->make($this->assetEntryMaker(), [
            'package'     => 'site/plugin',
            'name'        => 'outside',
            '--path'      => '../outside/entry.ts',
            '--no-loader' => true,
        ]);
    }

    public function testAssetEntryRejectsHandleThatBreaksOutOfTheSourceComment(): void
    {
        foreach (["evil\nalert(1)", 'evil */ body { } /*'] as $handle) {
            try {
                $this->make($this->assetEntryMaker(), [
                    'package'     => 'site/plugin',
                    'name'        => 'frontend',
                    '--handle'    => $handle,
                    '--no-loader' => true,
                ]);
                self::fail('Unsafe handle accepted.');
            } catch (\InvalidArgumentException) {
                self::assertDirectoryDoesNotExist($this->workspace . '/packages/plugin/Resources');
            }
        }
    }

    public function testBlockRefusesToOverwriteExistingBlockAssets(): void
    {
        $blockJson = $this->workspace . '/packages/plugin/Resources/block/hero/block.json';
        mkdir(dirname($blockJson), 0777, true);
        file_put_contents($blockJson, '{"name":"site/maintained"}');
        $maker = new MakeBlock(
            $this->contextResolver,
            new PackageServiceConfigurator(),
            new WebpackEncoreEntryConfigurator(),
            new JsonFile(),
        );

        $arguments = ['class' => 'Site\\Plugin\\Block\\HeroBlock', '--no-service' => true, '--with-view' => true];

        try {
            $this->make($maker, $arguments);
            $error = null;
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        self::assertStringContainsString('already exists', (string) $error);
        self::assertSame('{"name":"site/maintained"}', file_get_contents($blockJson));
        self::assertFileDoesNotExist($this->workspace . '/packages/plugin/src/Block/HeroBlock.php');

        unlink($blockJson);
        $this->make($maker, $arguments);
        self::assertFileExists($blockJson);
        self::assertStringContainsString('"attributes": {}', (string) file_get_contents($blockJson));
        self::assertFileExists($this->workspace . '/packages/plugin/Resources/views/block/hero.php');
    }

    public function testSympressPackageKeepsEmptyObjectsInRootComposerJson(): void
    {
        file_put_contents(
            $this->workspace . '/composer.json',
            '{"name": "site/root", "require": {"php": "^8.5"}, "require-dev": {}, "extra": {},'
            . ' "config": {"allow-plugins": {}}, "repositories": []}',
        );

        $this->make(new MakeSympressPackage($this->workspace, new JsonFile()), ['name' => 'site/events']);

        $root = (string) file_get_contents($this->workspace . '/composer.json');
        self::assertStringContainsString('"require-dev": {}', $root);
        self::assertStringContainsString('"extra": {}', $root);
        self::assertStringContainsString('"allow-plugins": {}', $root);
        self::assertStringContainsString('"repositories": []', $root);
        self::assertSame(
            ['php' => '^8.5', 'site/events' => '*'],
            json_decode($root, true, flags: \JSON_THROW_ON_ERROR)['require'],
        );
    }

    /** @param array<string, mixed> $arguments */
    private function make(AbstractMaker $maker, array $arguments): void
    {
        $command = new Command($maker::getCommandName());
        $maker->configureCommand($command, new InputConfiguration());
        $input = new ArrayInput($arguments, $command->getDefinition());
        $maker->generate($input, new ConsoleStyle($input, new BufferedOutput()), $this->generator());
    }

    private function assetEntryMaker(): MakeAssetEntry
    {
        return new MakeAssetEntry(
            $this->contextResolver,
            new PackageServiceConfigurator(),
            new WebpackEncoreEntryConfigurator(),
        );
    }

    private function generator(): PackageAwareGenerator
    {
        $fileManager = new FileManager(
            new Filesystem(),
            new PackageAwareAutoloaderUtil($this->autoloadResolver),
            new MakerFileLinkFormatter(),
            $this->workspace,
            null,
        );

        return new PackageAwareGenerator(
            $fileManager,
            'App',
            $this->autoloadResolver,
            null,
            new TemplateComponentGenerator(true, false, 'App'),
        );
    }
}
