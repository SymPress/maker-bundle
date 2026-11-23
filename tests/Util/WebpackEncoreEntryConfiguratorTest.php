<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Util\PackageContext;
use SymPress\MakerBundle\Util\WebpackEncoreEntryConfigurator;

final class WebpackEncoreEntryConfiguratorTest extends TestCase
{
    public function testDefaultConfigCleansOutputOutsideProduction(): void
    {
        $contents = $this->defaultConfigWithEntry('./Resources/ts/frontend.ts');

        self::assertStringContainsString('.cleanupOutputBeforeBuild(!Encore.isProduction() ?', $contents);
        self::assertStringNotContainsString('Encore.isProduction ?', $contents);
    }

    public function testEntryPathIsWrittenWithoutEscapedSlashes(): void
    {
        self::assertStringContainsString(
            ".addEntry('site-frontend', './Resources/ts/frontend.ts')",
            $this->defaultConfigWithEntry('./Resources/ts/frontend.ts'),
        );
    }

    private function defaultConfigWithEntry(string $sourcePath): string
    {
        $root = (string) realpath(sys_get_temp_dir());
        $update = (new WebpackEncoreEntryConfigurator())->entry(
            new PackageContext($root, 'site/root', $root . '/maker-webpack-' . bin2hex(random_bytes(5)), 'Site'),
            'site-frontend',
            $sourcePath,
            false,
        );
        self::assertNotNull($update);

        return $update->contents;
    }
}
