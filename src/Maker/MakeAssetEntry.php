<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use SymPress\MakerBundle\Util\PackageContext;
use SymPress\MakerBundle\Util\PackageContextResolver;
use SymPress\MakerBundle\Util\PackageServiceConfigurator;
use SymPress\MakerBundle\Util\WebpackEncoreEntryConfigurator;
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

final class MakeAssetEntry extends AbstractMaker
{
    public function __construct(
        private readonly PackageContextResolver $contextResolver,
        private readonly PackageServiceConfigurator $serviceConfigurator,
        private readonly WebpackEncoreEntryConfigurator $webpackConfigurator,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:asset-entry';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a package asset entry and wire it into Encore';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('package', InputArgument::REQUIRED, 'Composer package name or package root namespace')
            ->addArgument('name', InputArgument::REQUIRED, 'Entry name, e.g. frontend, admin, editor')
            ->addOption('location', null, InputOption::VALUE_REQUIRED, 'Asset location: frontend, admin, gutenberg, block', 'frontend')
            ->addOption('handle', null, InputOption::VALUE_REQUIRED, 'Asset handle')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Entry source path relative to the package')
            ->addOption('style', null, InputOption::VALUE_NONE, 'Create a style entry instead of a script entry')
            ->addOption('no-loader', null, InputOption::VALUE_NONE, 'Do not generate an asset config loader');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $context = $this->contextResolver->fromPackageOrClass((string) $input->getArgument('package'));
        $name = (string) $input->getArgument('name');
        $style = (bool) $input->getOption('style');
        $handle = (string) ($input->getOption('handle') ?: $this->defaultHandle($context, $name));
        $sourcePath = (string) ($input->getOption('path') ?: $this->defaultSourcePath($name, $style));
        $location = $this->assetLocation((string) $input->getOption('location'));
        $webpackUpdate = $this->webpackConfigurator->entry($context, $handle, $sourcePath, $style);

        if ($webpackUpdate !== null) {
            $generator->dumpFile($webpackUpdate->path, $webpackUpdate->contents);
        }

        $sourceFile = $this->sourceFile($context, $sourcePath);

        if (!is_file(sprintf('%s/%s', $context->projectDir, $sourceFile))) {
            $generator->dumpFile($sourceFile, $style ? $this->styleSource($handle) : $this->scriptSource($handle));
        }

        if (!$input->getOption('no-loader')) {
            $this->ensureConfigLoaderInterface($context, $generator);
            $loaderClass = $context->className(sprintf(
                'ConfigLoader\\%sConfigLoader',
                Str::asClassName($name, 'Asset'),
            ));
            $generator->generateClass(
                $loaderClass,
                __DIR__ . '/../../Resources/skeleton/asset_entry/AssetConfigLoader.tpl.php',
                [
                    'asset_handle'   => $handle,
                    'asset_location' => $location,
                ],
            );

            $serviceUpdate = $this->serviceConfigurator->assetLoader($context, $loaderClass);

            if ($serviceUpdate !== null) {
                $generator->dumpFile($serviceUpdate->path, $serviceUpdate->contents);
            }
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text(sprintf('Asset entry "%s" was added to "%s".', $handle, $context->packageName));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function defaultHandle(PackageContext $context, string $name): string
    {
        $normalized = str_replace('_', '-', Str::asSnakeCase($name));

        return match ($normalized) {
            'frontend' => $context->assetHandlePrefix(),
            'editor', 'gutenberg' => $context->assetHandlePrefix() . '-editor',
            'admin' => $context->assetHandlePrefix() . '-admin',
            default => sprintf('%s-%s', $context->assetHandlePrefix(), $normalized),
        };
    }

    private function defaultSourcePath(string $name, bool $style): string
    {
        $path = str_replace('_', '-', Str::asSnakeCase($name));

        if ($style) {
            return sprintf('./Resources/scss/%s.scss', $path);
        }

        return sprintf('./Resources/ts/%s.ts', $path);
    }

    private function sourceFile(PackageContext $context, string $sourcePath): string
    {
        return $context->packageRelativePath(ltrim($sourcePath, './'));
    }

    private function assetLocation(string $location): string
    {
        return match ($location) {
            'admin', 'backend' => 'Asset::BACKEND',
            'gutenberg', 'editor', 'block-editor' => 'Asset::BLOCK_EDITOR_ASSETS',
            'block' => 'Asset::BLOCK_ASSETS',
            default => 'Asset::FRONTEND',
        };
    }

    private function ensureConfigLoaderInterface(PackageContext $context, Generator $generator): void
    {
        $file = sprintf('%s/src/ConfigLoader/ConfigLoaderInterface.php', $context->packagePath);

        if (is_file($file)) {
            return;
        }

        $generator->generateClass(
            $context->className('ConfigLoader\\ConfigLoaderInterface'),
            __DIR__ . '/../../Resources/skeleton/config_loader/ConfigLoaderInterface.tpl.php',
        );
    }

    private function scriptSource(string $handle): string
    {
        return sprintf("// %s entry\n", $handle);
    }

    private function styleSource(string $handle): string
    {
        return sprintf("/* %s entry */\n", $handle);
    }
}
