<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use SymPress\MakerBundle\Util\PackageContextResolver;
use SymPress\MakerBundle\Util\PackageServiceConfigurator;
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

final class MakeConfigLoader extends AbstractMaker
{
    public function __construct(
        private readonly PackageContextResolver $contextResolver,
        private readonly PackageServiceConfigurator $serviceConfigurator,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:config-loader';
    }

    public static function getCommandDescription(): string
    {
        return 'Create frontend and Gutenberg asset config loaders for a package';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('package', InputArgument::REQUIRED, 'Composer package name or package root namespace')
            ->addOption('frontend-handle', null, InputOption::VALUE_REQUIRED, 'Frontend asset handle')
            ->addOption('editor-handle', null, InputOption::VALUE_REQUIRED, 'Block editor asset handle')
            ->addOption('localize-var', null, InputOption::VALUE_REQUIRED, 'JavaScript localization variable')
            ->addOption('no-service', null, InputOption::VALUE_NONE, 'Do not update the package service config');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $context = $this->contextResolver->fromPackageOrClass((string) $input->getArgument('package'));
        $frontendHandle = (string) ($input->getOption('frontend-handle') ?: $context->assetHandlePrefix());
        $editorHandle = (string) ($input->getOption('editor-handle') ?: $context->assetHandlePrefix() . '-editor');
        $localizeVar = (string) ($input->getOption('localize-var') ?: Str::asClassName($context->assetHandlePrefix()));
        $interfaceClass = $context->className('ConfigLoader\\ConfigLoaderInterface');
        $frontendClass = $context->className('ConfigLoader\\FrontendConfigLoader');
        $gutenbergClass = $context->className('ConfigLoader\\GutenbergConfigLoader');

        $generator->generateClass(
            $interfaceClass,
            __DIR__ . '/../../Resources/skeleton/config_loader/ConfigLoaderInterface.tpl.php',
        );
        $generator->generateClass(
            $frontendClass,
            __DIR__ . '/../../Resources/skeleton/config_loader/FrontendConfigLoader.tpl.php',
            [
                'frontend_handle' => $frontendHandle,
            ],
        );
        $generator->generateClass(
            $gutenbergClass,
            __DIR__ . '/../../Resources/skeleton/config_loader/GutenbergConfigLoader.tpl.php',
            [
                'editor_handle' => $editorHandle,
                'localize_var'  => $localizeVar,
            ],
        );

        if (!$input->getOption('no-service')) {
            $update = $this->serviceConfigurator->configLoaders($context, $frontendClass, $gutenbergClass);

            if ($update !== null) {
                $generator->dumpFile($update->path, $update->contents);
            }
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text(sprintf('Config loaders were created for "%s".', $context->packageName));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
