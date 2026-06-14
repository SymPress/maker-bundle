<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use SymPress\MakerBundle\Tests\Fixtures\ArrayComposerPackageLocator;

final class PackageAutoloadResolverTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-maker-' . bin2hex(random_bytes(6));
        mkdir($this->workspace . '/packages/theme', 0777, true);
        mkdir($this->workspace . '/packages/plugin', 0777, true);

        $this->writeComposerJson(
            $this->workspace . '/packages/theme/composer.json',
            [
                'name'     => 'site/theme',
                'type'     => 'wordpress-theme',
                'autoload' => [
                    'psr-4' => [
                        'Site\\Theme\\' => 'src/',
                    ],
                ],
            ],
        );
        $this->writeComposerJson(
            $this->workspace . '/packages/plugin/composer.json',
            [
                'name'         => 'site/plugin',
                'type'         => 'wordpress-plugin',
                'autoload'     => [
                    'psr-4' => [
                        'Site\\Plugin\\' => 'src/',
                    ],
                ],
                'autoload-dev' => [
                    'psr-4' => [
                        'Site\\Plugin\\Tests\\' => 'tests/',
                    ],
                ],
            ],
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testResolvesClassPathFromMostSpecificPackageNamespace(): void
    {
        $resolver = $this->resolver();

        self::assertSame(
            $this->workspace . '/packages/theme/src/Hook/Assets.php',
            $resolver->getPathForFutureClass('Site\\Theme\\Hook\\Assets'),
        );
        self::assertSame(
            $this->workspace . '/packages/plugin/tests/Service/FooTest.php',
            $resolver->getPathForFutureClass('Site\\Plugin\\Tests\\Service\\FooTest'),
        );
    }

    public function testReportsNamespaceConfigurationForPackageAndWorkspaceRoots(): void
    {
        $resolver = $this->resolver();

        self::assertTrue($resolver->isNamespaceConfiguredToAutoload('Site\\Theme'));
        self::assertTrue($resolver->isNamespaceConfiguredToAutoload('Site'));
        self::assertFalse($resolver->isNamespaceConfiguredToAutoload('Other'));
    }

    public function testUsesThemeNamespaceAsDefaultRootNamespace(): void
    {
        self::assertSame('Site\\Theme', $this->resolver()->defaultRootNamespace());
    }

    public function testFindsPackageRootNamespaceForClass(): void
    {
        self::assertSame(
            'Site\\Plugin',
            $this->resolver()->packageRootNamespaceForClass('Site\\Plugin\\Message\\PingMessage'),
        );
    }

    public function testExtractsPackageClassNameFromMakerPrefixedClassName(): void
    {
        self::assertSame(
            'Site\\Theme\\Serializer\\CustomEncoder',
            $this->resolver()->absolutePackageClassName(
                '\\Serializer\\Site\\\\Theme\\\\Serializer\\\\CustomEncoder',
            ),
        );
    }

    private function resolver(): PackageAutoloadResolver
    {
        return new PackageAutoloadResolver(
            new ArrayComposerPackageLocator(
                [
                    'site/theme'  => $this->workspace . '/packages/theme',
                    'site/plugin' => $this->workspace . '/packages/plugin',
                ],
            ),
        );
    }

    /** @param array<string, mixed> $data */
    private function writeComposerJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
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
