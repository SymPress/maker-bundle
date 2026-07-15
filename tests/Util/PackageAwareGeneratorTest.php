<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use SymPress\MakerBundle\Tests\Fixtures\ArrayComposerPackageLocator;
use SymPress\MakerBundle\Util\PackageAwareAutoloaderUtil;
use SymPress\MakerBundle\Util\PackageAwareGenerator;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Bundle\MakerBundle\Util\TemplateComponentGenerator;
use Symfony\Component\Filesystem\Filesystem;

final class PackageAwareGeneratorTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-maker-generator-' . bin2hex(random_bytes(6));
        mkdir($this->workspace . '/packages/plugin', 0777, true);

        file_put_contents(
            $this->workspace . '/packages/plugin/composer.json',
            json_encode(
                [
                    'name'     => 'site/plugin',
                    'type'     => 'wordpress-plugin',
                    'autoload' => [
                        'psr-4' => [
                            'Site\\Plugin\\' => 'src/',
                        ],
                    ],
                ],
                \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR,
            ),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testRelativeFollowUpClassesUsePreviouslyResolvedPackageRoot(): void
    {
        $generator = $this->generator();

        $messageClass = $generator->createClassNameDetails(
            'Site\\Plugin\\Message\\PingMessage',
            'Message\\',
            'Message',
        );
        $handlerClass = $generator->createClassNameDetails(
            $messageClass->getRelativeNameWithoutSuffix(),
            'MessageHandler\\',
            'Handler',
        );

        self::assertSame('Site\\Plugin\\Message\\PingMessage', $messageClass->getFullName());
        self::assertSame('Site\\Plugin\\MessageHandler\\Message\\PingHandler', $handlerClass->getFullName());
    }

    public function testRelativeFollowUpClassWithEmptyNamespacePrefixUsesPackageRoot(): void
    {
        $generator = $this->generator();

        $rootClass = $generator->createClassNameDetails(
            'Site\\Plugin\\Dashboard',
            '',
        );
        $followUpClass = $generator->createClassNameDetails(
            'Widget',
            '',
        );

        self::assertSame('Site\\Plugin\\Dashboard', $rootClass->getFullName());
        self::assertSame('Site\\Plugin\\Widget', $followUpClass->getFullName());
    }

    public function testGenerateClassNormalizesEmbeddedPackageClassData(): void
    {
        $generator = $this->generator();
        $template = $this->workspace . '/class.tpl.php';
        file_put_contents(
            $template,
            <<<'PHP'
<?= "<?php\n" ?>

namespace <?= $class_data->getNamespace(); ?>;

<?= $class_data->getClassDeclaration(); ?>
{
}
PHP
            ,
        );

        $classData = ClassData::create(
            class: 'Controller\Site\Plugin\Controller\Dashboard',
            suffix: 'Controller',
        );

        $path = $generator->generateClass(
            $classData->getFullClassName(),
            $template,
            [
                'class_data' => $classData,
            ],
        );
        $generator->writeChanges();

        self::assertSame('packages/plugin/src/Controller/DashboardController.php', $path);
        self::assertStringContainsString(
            'namespace Site\\Plugin\\Controller;',
            file_get_contents($this->workspace . '/' . $path),
        );
    }

    public function testHookSkeletonGeneratesExpectedPackageClass(): void
    {
        $generator = $this->generator();
        $path = $generator->generateClass(
            'Site\\Plugin\\Hook\\PublishHook',
            __DIR__ . '/../../Resources/skeleton/hook/Hook.tpl.php',
            [
                'method_name' => 'register',
                'type'        => 'action',
            ],
        );
        $generator->writeChanges();

        self::assertSame('packages/plugin/src/Hook/PublishHook.php', $path);
        self::assertSame(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Site\Plugin\Hook;

final class PublishHook
{
    public function register(): void
    {
    }
}
PHP . "\n",
            file_get_contents($this->workspace . '/' . $path),
        );
    }

    /** @param array<string, mixed> $variables */
    #[DataProvider('localSkeletonContracts')]
    public function testLocalSkeletonGeneratesStablePackageClass(
        string $className,
        string $skeleton,
        array $variables,
        string $expectedPath,
        string $expectedHash,
    ): void {

        $generator = $this->generator();
        $path = $generator->generateClass(
            $className,
            __DIR__ . '/../../Resources/skeleton/' . $skeleton,
            $variables,
        );
        $generator->writeChanges();

        self::assertSame($expectedPath, $path);
        self::assertSame(
            $expectedHash,
            hash_file('sha256', $this->workspace . '/' . $path),
        );
    }

    /** @return iterable<string, array{string, string, array<string, mixed>, string, string}> */
    public static function localSkeletonContracts(): iterable
    {
        yield 'data provider' => [
            'Site\\Plugin\\DataProvider\\DashboardDataProvider',
            'data_provider/DataProvider.tpl.php',
            ['method_name' => 'provide'],
            'packages/plugin/src/DataProvider/DashboardDataProvider.php',
            'd910db0e725e8aa8543b2967a68534bc00260ab38ce5d9b07b390480edd71199',
        ];
        yield 'config loader interface' => [
            'Site\\Plugin\\ConfigLoader\\ConfigLoaderInterface',
            'config_loader/ConfigLoaderInterface.tpl.php',
            [],
            'packages/plugin/src/ConfigLoader/ConfigLoaderInterface.php',
            '391ede28c3d171c8a3147fb70258be857e6bde27c016c9236af88d9ba30490e2',
        ];
        yield 'frontend config loader' => [
            'Site\\Plugin\\ConfigLoader\\FrontendConfigLoader',
            'config_loader/FrontendConfigLoader.tpl.php',
            ['frontend_handle' => 'site-plugin'],
            'packages/plugin/src/ConfigLoader/FrontendConfigLoader.php',
            '120aa77321e429423f2697388a64bdb184b2c23b2d8b4094636c3bdf2f69b0d8',
        ];
        yield 'Gutenberg config loader' => [
            'Site\\Plugin\\ConfigLoader\\GutenbergConfigLoader',
            'config_loader/GutenbergConfigLoader.tpl.php',
            [
                'editor_handle' => 'site-plugin-editor',
                'localize_var'  => 'sitePlugin',
            ],
            'packages/plugin/src/ConfigLoader/GutenbergConfigLoader.php',
            '7ba1186c9f62ef472262ccdfe7bcb5c14114f43eb832eb19b300ab92dcf2e719',
        ];
        yield 'asset config loader' => [
            'Site\\Plugin\\ConfigLoader\\AdminConfigLoader',
            'asset_entry/AssetConfigLoader.tpl.php',
            [
                'asset_handle'   => 'site-plugin-admin',
                'asset_location' => 'Asset::BACKEND',
            ],
            'packages/plugin/src/ConfigLoader/AdminConfigLoader.php',
            '136b3b67123bc28516be76e2c714ae6e0db33114e1e0fc83a1ff4a9004bd6252',
        ];
        yield 'block' => [
            'Site\\Plugin\\Block\\HeroBlock',
            'block/Block.tpl.php',
            [
                'block_name'      => 'site/hero',
                'title'           => 'Hero',
                'description'     => 'Hero block.',
                'category'        => 'design',
                'icon'            => 'cover-image',
                'text_domain'     => 'site-plugin',
                'editor_handle'   => 'site-plugin-hero-editor',
                'frontend_handle' => 'site-plugin-hero',
                'localizable'     => true,
                'js_config_var'   => 'heroBlock',
                'with_frontend'   => true,
                'with_view'       => true,
                'view_path'       => '../../Resources/views/block/hero.php',
            ],
            'packages/plugin/src/Block/HeroBlock.php',
            '12c86062e6ecf7eee3ce0d6be96d446f39eb7790cc2cf94ce23bbed3077b290b',
        ];
    }

    private function generator(): PackageAwareGenerator
    {
        $resolver = new PackageAutoloadResolver(
            new ArrayComposerPackageLocator(
                [
                    'site/plugin' => $this->workspace . '/packages/plugin',
                ],
            ),
        );
        $fileManager = new FileManager(
            new Filesystem(),
            new PackageAwareAutoloaderUtil($resolver),
            new MakerFileLinkFormatter(),
            $this->workspace,
            null,
        );

        return new PackageAwareGenerator(
            $fileManager,
            'App',
            $resolver,
            null,
            new TemplateComponentGenerator(true, false, 'App'),
        );
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
