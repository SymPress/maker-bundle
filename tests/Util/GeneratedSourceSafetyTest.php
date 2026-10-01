<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Util\PackageContext;
use SymPress\MakerBundle\Util\ProjectPath;
use SymPress\MakerBundle\Util\SourceLiteral;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Component\Process\Process;

final class GeneratedSourceSafetyTest extends TestCase
{
    public function testBlockSkeletonWithLiteralAttacksIsValidPhp(): void
    {
        $value = "O'Reilly \\\ path \"quoted\"\nUnicode Ä \u{2028}\u{2029}</script>";
        $variables = [
            'namespace'     => 'Example',
        'class_name'        => 'DangerBlock',
        'localizable'       => true,
            'block_name'    => $value,
        'title'             => $value,
        'description'       => $value,
        'category'          => $value,
            'icon'          => $value,
        'text_domain'       => $value,
        'editor_handle'     => $value,
        'frontend_handle'   => $value,
            'js_config_var' => $value,
        'with_frontend'     => true,
        'with_view'         => false,
        'view_path'         => $value,
        ];
        $file = tempnam(sys_get_temp_dir(), 'maker-literal-');
        self::assertIsString($file);
        file_put_contents($file, (new \ReflectionClass(FileManager::class))->newInstanceWithoutConstructor()->parseTemplate(dirname(__DIR__, 2) . '/Resources/skeleton/block/Block.tpl.php', $variables));
        try {
            $process = new Process([PHP_BINARY, '-l', $file]);
            self::assertSame(0, $process->run(), $process->getOutput() . $process->getErrorOutput());
            self::assertStringNotContainsString('<div data-block="O\'Reilly', (string) file_get_contents($file));
        } finally {
            unlink($file);
        }
        self::assertStringContainsString('\\u2028', SourceLiteral::javascript($value));
        self::assertStringContainsString('\\u2029', SourceLiteral::javascript($value));
    }

    public function testOutputPathRejectsTraversalAbsoluteAndSymlinkEscapes(): void
    {
        $root = sys_get_temp_dir() . '/maker-root-' . bin2hex(random_bytes(5));
        $outside = $root . '-outside';
        mkdir($root, 0700);
        mkdir($outside, 0700);
        symlink($outside, $root . '/link');
        try {
            self::assertSame('packages/new', ProjectPath::relative($root, 'packages/new'));
            $context = new PackageContext($root, 'site/root', $root, 'Site');
            self::assertSame('Resources/ts/block/example.ts', $context->packageRelativePath('Resources/ts/block/example.ts'));
            self::assertSame('config/services.php', $context->relativePath($root . '/config/services.php'));
            foreach (['../escape', '/tmp/escape', 'link/nonexistent', "invalid\0path", './packages/../../escape'] as $path) {
                try {
                    ProjectPath::relative($root, $path);
                    self::fail('Unsafe path accepted.');
                } catch (\InvalidArgumentException) {
                    self::assertTrue(true);
                }
            }
        } finally {
            unlink($root . '/link');
            rmdir($root);
            rmdir($outside);
        }
    }
}
