<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Util\PackageContext;
use SymPress\MakerBundle\Util\PackageServiceConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class PackageServiceConfiguratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/maker-services-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/Resources/config', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testYamlDefinitionIsAddedToTheServicesBlock(): void
    {
        $services = $this->addService(<<<'YAML'
parameters:
    site.flag: true

services:
    _defaults:
        autowire: true

when@dev:
    parameters:
        site.debug: true
YAML);

        self::assertArrayHasKey('Site\\Service\\Example', $services['services']);
        self::assertSame(['parameters' => ['site.debug' => true]], $services['when@dev']);
        self::assertSame(['site.flag' => true], $services['parameters']);
    }

    public function testYamlDefinitionAddsServicesBlockWhenMissing(): void
    {
        $services = $this->addService("parameters:\n    site.flag: true\n");

        self::assertArrayHasKey('Site\\Service\\Example', $services['services']);
    }

    public function testYamlInlineServicesKeyIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->addService("services: {}\n");
    }

    /** @return array<string, mixed> */
    private function addService(string $yaml): array
    {
        file_put_contents($this->root . '/Resources/config/services.yaml', $yaml);
        $update = (new PackageServiceConfigurator())->simple(
            new PackageContext($this->root, 'site/root', $this->root, 'Site'),
            'Site\\Service\\Example',
        );
        self::assertNotNull($update);
        self::assertSame('Resources/config/services.yaml', $update->path);

        $parsed = Yaml::parse($update->contents);
        self::assertIsArray($parsed);

        return $parsed;
    }
}
