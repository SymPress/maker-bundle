<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Util;

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
