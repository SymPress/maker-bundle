<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use SymPress\MakerBundle\Util\JsonFile;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class MakeSympressPackage extends AbstractMaker
{
    private const TYPE_ALIASES = [
        'mu-plugin'           => 'wordpress-muplugin',
        'wordpress-mu-plugin' => 'wordpress-muplugin',
    ];

    private const SUPPORTED_TYPES = [
        'library',
        'package',
        'wordpress-muplugin',
        'wordpress-plugin',
        'wordpress-theme',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly JsonFile $jsonFile,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:sympress-package';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new SymPress package';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'Composer package name, e.g. <fg=yellow>brianvarskonst/events</>')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Root PHP namespace for the package')
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'Composer package type: library, package, wordpress-plugin, wordpress-muplugin, wordpress-theme',
                'wordpress-plugin',
            )
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Composer package description')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Package path below the project root')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Add the package to require-dev in root composer.json')
            ->addOption('no-root-composer', null, InputOption::VALUE_NONE, 'Do not add the package to root composer.json');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $packageName = strtolower((string) $input->getArgument('name'));
        $this->validatePackageName($packageName);

        $packageType = $this->normalizePackageType((string) $input->getOption('type'));
        $packageDirectory = $this->packageDirectory($packageName);
        $packagePath = (string) ($input->getOption('path') ?: sprintf('packages/%s', $packageDirectory));
        $absolutePackagePath = sprintf('%s/%s', rtrim($this->projectDir, '/'), trim($packagePath, '/'));

        if (is_dir($absolutePackagePath)) {
            throw new \RuntimeException(sprintf('Package directory "%s" already exists.', $packagePath));
        }

        $namespace = trim((string) ($input->getOption('namespace') ?: $this->namespace($packageName)), '\\');
        $bundleClass = $this->isKernelPackage($packageType)
            ? sprintf('%s\\%s', $namespace, $this->bundleClassName($packageName))
            : null;
        $description = (string) ($input->getOption('description') ?: sprintf('SymPress package for %s.', $packageName));

        $generator->dumpFile(
            sprintf('%s/composer.json', $packagePath),
            $this->jsonFile->encode($this->packageComposer($packageName, $description, $packageType, $namespace, $bundleClass)),
        );
        $generator->dumpFile(sprintf('%s/README.md', $packagePath), $this->readme($packageName, $description));
        $generator->dumpFile(sprintf('%s/tests/bootstrap.php', $packagePath), $this->testBootstrap());
        $generator->dumpFile(sprintf('%s/phpunit.xml.dist', $packagePath), $this->phpunitConfig());
        $generator->dumpFile(sprintf('%s/phpcs.xml.dist', $packagePath), $this->phpcsConfig());

        if ($bundleClass !== null) {
            $generator->dumpFile(sprintf('%s/Resources/config/services.php', $packagePath), $this->servicesConfig());
            $generator->dumpFile(sprintf('%s/src/%s.php', $packagePath, Str::getShortClassName($bundleClass)), $this->bundle($namespace, $bundleClass));
        } else {
            $generator->dumpFile(sprintf('%s/src/.gitkeep', $packagePath), '');
        }

        if ($this->hasWordPressPluginEntry($packageType) && $bundleClass !== null) {
            $generator->dumpFile(
                sprintf('%s/%s.php', $packagePath, $packageDirectory),
                $this->pluginEntry($packageName, $description, $namespace, $bundleClass),
            );
        }

        if ($packageType === 'wordpress-theme') {
            $generator->dumpFile(sprintf('%s/functions.php', $packagePath), $this->themeFunctions($namespace));
            $generator->dumpFile(sprintf('%s/style.css', $packagePath), $this->themeStylesheet($packageName, $description));
        }

        if (!$input->getOption('no-root-composer')) {
            $generator->dumpFile('composer.json', $this->rootComposerWithPackage($packageName, (bool) $input->getOption('dev')));
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text(sprintf('Package "%s" (%s) was created at %s.', $packageName, $packageType, $packagePath));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function validatePackageName(string $packageName): void
    {
        if (preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $packageName) !== 1) {
            throw new \InvalidArgumentException('Package name must use Composer vendor/name format.');
        }
    }

    private function packageDirectory(string $packageName): string
    {
        [$vendor, $name] = explode('/', $packageName, 2);

        return $vendor === 'sympress' ? $name : sprintf('%s-%s', $vendor, $name);
    }

    private function namespace(string $packageName): string
    {
        [$vendor, $name] = explode('/', $packageName, 2);
        $vendorNamespace = $vendor === 'sympress' ? 'SymPress' : Str::asClassName($vendor);

        return sprintf('%s\\%s', $vendorNamespace, Str::asClassName($name));
    }

    private function bundleClassName(string $packageName): string
    {
        [, $name] = explode('/', $packageName, 2);

        return Str::asClassName($name, 'Bundle');
    }

    /**
     * @return array<string, mixed>
     */
    private function packageComposer(
        string $packageName,
        string $description,
        string $packageType,
        string $namespace,
        ?string $bundleClass,
    ): array {
        $require = [
            'php' => '^8.5',
        ];

        if ($bundleClass !== null) {
            $require['sympress/kernel'] = '@dev';
        }

        $composer = [
            'name'              => $packageName,
            'description'       => $description,
            'type'              => $packageType,
            'license'           => 'GPL-2.0-or-later',
            'require'           => $require,
            'require-dev'       => [
                'phpunit/phpunit'          => '^11.5',
                'sympress/coding-standards' => '*',
            ],
            'autoload'          => [
                'psr-4' => [
                    $namespace . '\\' => 'src/',
                ],
            ],
            'autoload-dev'      => [
                'psr-4' => [
                    $namespace . '\\Tests\\' => 'tests/',
                ],
            ],
            'scripts'           => [
                'cs'     => [
                    'Composer\\Config::disableProcessTimeout',
                    'phpcs --standard=phpcs.xml.dist',
                ],
                'cs:fix' => [
                    'Composer\\Config::disableProcessTimeout',
                    'phpcbf --standard=phpcs.xml.dist',
                ],
                'tests'  => [
                    'Composer\\Config::disableProcessTimeout',
                    'phpunit --configuration phpunit.xml.dist --no-coverage',
                ],
                'qa'     => ['@cs', '@tests'],
            ],
            'config'            => [
                'sort-packages'      => true,
                'optimize-autoloader' => true,
                'allow-plugins'       => [
                    'dealerdirect/phpcodesniffer-composer-installer' => true,
                ],
            ],
            'minimum-stability' => 'dev',
            'prefer-stable'     => true,
            'repositories'      => [
                [
                    'type'    => 'path',
                    'url'     => './../*',
                    'options' => [
                        'symlink' => true,
                    ],
                ],
            ],
        ];

        if ($bundleClass !== null) {
            $composer['extra'] = [
                'kernel' => [
                    'bundle' => $bundleClass,
                    'entry'  => $this->entry($packageName, $packageType),
                ],
            ];
        }

        return $composer;
    }

    private function entry(string $packageName, string $packageType): string
    {
        [, $name] = explode('/', $packageName, 2);

        if ($packageType === 'library' || $packageType === 'wordpress-theme') {
            return $name;
        }

        return sprintf('%s/%s.php', $name, $this->packageDirectory($packageName));
    }

    private function rootComposerWithPackage(string $packageName, bool $dev): string
    {
        $rootComposer = sprintf('%s/composer.json', rtrim($this->projectDir, '/'));
        $data = $this->jsonFile->read($rootComposer);
        $section = $dev ? 'require-dev' : 'require';
        $require = $data[$section] ?? [];

        if (!is_array($require)) {
            $require = [];
        }

        $require[$packageName] = '*';
        ksort($require);
        $data[$section] = $require;

        return $this->jsonFile->encode($data);
    }

    private function normalizePackageType(string $packageType): string
    {
        $packageType = strtolower(trim($packageType));
        $packageType = self::TYPE_ALIASES[$packageType] ?? $packageType;

        if (!in_array($packageType, self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported package type "%s". Supported types are: %s.',
                $packageType,
                implode(', ', self::SUPPORTED_TYPES),
            ));
        }

        return $packageType;
    }

    private function isKernelPackage(string $packageType): bool
    {
        return $packageType !== 'package';
    }

    private function hasWordPressPluginEntry(string $packageType): bool
    {
        return $packageType === 'wordpress-plugin' || $packageType === 'wordpress-muplugin';
    }

    private function readme(string $packageName, string $description): string
    {
        return sprintf(
            "# %s\n\n%s\n",
            $packageName,
            $description,
        );
    }

    private function servicesConfig(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();
};
PHP . "\n";
    }

    private function bundle(string $namespace, string $bundleClass): string
    {
        return sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

use SymPress\Kernel\Bundle\AbstractBundle;

final class %s extends AbstractBundle
{
}
PHP,
            $namespace,
            Str::getShortClassName($bundleClass),
        ) . "\n";
    }

    private function pluginEntry(string $packageName, string $description, string $namespace, string $bundleClass): string
    {
        return sprintf(
            <<<'PHP'
<?php

/**
 * Plugin Name: %s
 * Description: %s
 * Version: 0.1.0
 * Requires PHP: 8.5
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace %s;

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists(%s::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}
PHP,
            Str::asHumanWords($packageName),
            $description,
            $namespace,
            Str::getShortClassName($bundleClass),
        ) . "\n";
    }

    private function themeFunctions(string $namespace): string
    {
        return sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

use SymPress\Kernel\App;

if (!class_exists(App::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!class_exists(App::class)) {
    return;
}
PHP,
            $namespace,
        ) . "\n";
    }

    private function themeStylesheet(string $packageName, string $description): string
    {
        [, $name] = explode('/', $packageName, 2);

        return sprintf(
            <<<'CSS'
/*
Theme Name: %s
Description: %s
Requires PHP: 8.5
Version: 0.1.0
License: GPL-2.0-or-later
Text Domain: %s
*/
CSS,
            Str::asHumanWords($name),
            $description,
            $name,
        ) . "\n";
    }

    private function testBootstrap(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
PHP . "\n";
    }

    private function phpunitConfig(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="Package Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML . "\n";
    }

    private function phpcsConfig(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<ruleset name="SymPress Package Coding Standard">
    <config name="testVersion" value="8.5-" />
    <arg name="colors" />
    <arg value="sp" />

    <rule ref="SymPress-Plugin" />

    <file>src</file>
    <file>tests</file>
</ruleset>
XML . "\n";
    }
}
