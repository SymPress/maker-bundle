<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use SymPress\MakerBundle\Util\JsonFile;
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

final class MakeBlock extends AbstractMaker
{
    public function __construct(
        private readonly PackageContextResolver $contextResolver,
        private readonly PackageServiceConfigurator $serviceConfigurator,
        private readonly WebpackEncoreEntryConfigurator $webpackConfigurator,
        private readonly JsonFile $jsonFile,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:block';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a Gutenberg block for a package';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('class', InputArgument::REQUIRED, 'Block class name or FQCN')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Gutenberg block name, e.g. vendor/example')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Block title')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Block description')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Block category', 'widgets')
            ->addOption('icon', null, InputOption::VALUE_REQUIRED, 'Dashicon or block icon name', 'block-default')
            ->addOption('localizable', null, InputOption::VALUE_NONE, 'Implement LocalizableBlockInterface')
            ->addOption('with-view', null, InputOption::VALUE_NONE, 'Generate a PHP view template')
            ->addOption('with-frontend', null, InputOption::VALUE_NONE, 'Generate a frontend script entry')
            ->addOption('no-service', null, InputOption::VALUE_NONE, 'Do not update the package service config');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $classDetails = $generator->createClassNameDetails((string) $input->getArgument('class'), 'Block\\', 'Block');
        $context = $this->contextResolver->fromClassName($classDetails->getFullName());
        $baseName = Str::removeSuffix($classDetails->getShortName(), 'Block');
        $slug = str_replace('_', '-', Str::asSnakeCase($baseName));
        $blockName = (string) ($input->getOption('name') ?: $this->defaultBlockName($context, $slug));
        $title = (string) ($input->getOption('title') ?: Str::asHumanWords($baseName));
        $description = (string) ($input->getOption('description') ?: sprintf('%s block.', $title));
        $editorHandle = sprintf('%s-%s-editor', $context->assetHandlePrefix(), $slug);
        $frontendHandle = sprintf('%s-%s', $context->assetHandlePrefix(), $slug);
        $editorSource = sprintf('./Resources/ts/block/%s-editor.tsx', $slug);
        $frontendSource = sprintf('./Resources/ts/block/%s.ts', $slug);
        $withFrontend = (bool) $input->getOption('with-frontend');
        $withView = (bool) $input->getOption('with-view');
        $localizable = (bool) $input->getOption('localizable');

        $this->ensureConfigLoaderInterface($context, $generator);
        $generator->generateClass(
            $classDetails->getFullName(),
            __DIR__ . '/../../Resources/skeleton/block/Block.tpl.php',
            [
                'block_name'      => $blockName,
                'title'           => $title,
                'description'     => $description,
                'category'        => (string) $input->getOption('category'),
                'icon'            => (string) $input->getOption('icon'),
                'text_domain'     => $context->textDomain(),
                'editor_handle'   => $editorHandle,
                'frontend_handle' => $frontendHandle,
                'localizable'     => $localizable,
                'js_config_var'   => Str::asLowerCamelCase($baseName),
                'with_frontend'   => $withFrontend,
                'with_view'       => $withView,
                'view_path'       => sprintf('../../Resources/views/block/%s.php', $slug),
            ],
        );
        $generator->dumpFile(
            $context->packageRelativePath(sprintf('Resources/block/%s/block.json', $slug)),
            $this->jsonFile->encode($this->blockMetadata(
                $blockName,
                $title,
                $description,
                (string) $input->getOption('category'),
                (string) $input->getOption('icon'),
                $editorHandle,
                $withFrontend ? $frontendHandle : null,
            )),
        );
        $generator->dumpFile(
            $context->packageRelativePath(ltrim($editorSource, './')),
            $this->editorSource($blockName, $title, $context->textDomain()),
        );

        $webpackUpdate = $this->webpackConfigurator->entry($context, $editorHandle, $editorSource, false);

        if ($webpackUpdate !== null) {
            $generator->dumpFile($webpackUpdate->path, $webpackUpdate->contents);
        }

        $assetLoaders = [
            $this->assetLoader($context, $generator, $baseName . 'Editor', $editorHandle, 'Asset::BLOCK_EDITOR_ASSETS'),
        ];

        if ($withFrontend) {
            $generator->dumpFile(
                $context->packageRelativePath(ltrim($frontendSource, './')),
                $this->frontendSource($blockName),
            );

            $frontendWebpackUpdate = $this->webpackConfigurator->entry($context, $frontendHandle, $frontendSource, false);

            if ($frontendWebpackUpdate !== null) {
                $generator->dumpFile($frontendWebpackUpdate->path, $frontendWebpackUpdate->contents);
            }

            $assetLoaders[] = $this->assetLoader($context, $generator, $baseName . 'Frontend', $frontendHandle, 'Asset::FRONTEND');
        }

        if ($withView) {
            $generator->dumpFile(
                $context->packageRelativePath(sprintf('Resources/views/block/%s.php', $slug)),
                "<div data-block=\"{$blockName}\"></div>\n",
            );
        }

        if (!$input->getOption('no-service')) {
            $serviceUpdate = $this->serviceConfigurator->blockWithAssetLoaders(
                $context,
                $classDetails->getFullName(),
                $localizable,
                $assetLoaders,
            );

            if ($serviceUpdate !== null) {
                $generator->dumpFile($serviceUpdate->path, $serviceUpdate->contents);
            }
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text(sprintf('Block "%s" was created.', $blockName));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function defaultBlockName(PackageContext $context, string $slug): string
    {
        [$vendor] = explode('/', $context->packageName, 2);

        return sprintf('%s/%s', $vendor, $slug);
    }

    /**
     * @return array<string, mixed>
     */
    private function blockMetadata(
        string $blockName,
        string $title,
        string $description,
        string $category,
        string $icon,
        string $editorHandle,
        ?string $frontendHandle,
    ): array {

        $metadata = [
            'apiVersion'   => 2,
            'name'         => $blockName,
            'title'        => $title,
            'description'  => $description,
            'category'     => $category,
            'icon'         => $icon,
            'editorScript' => $editorHandle,
            'supports'     => [
                'html' => false,
            ],
            'attributes'   => [],
        ];

        if ($frontendHandle !== null) {
            $metadata['script'] = $frontendHandle;
        }

        return $metadata;
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

    private function assetLoader(
        PackageContext $context,
        Generator $generator,
        string $name,
        string $handle,
        string $location,
    ): string {

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

        return $loaderClass;
    }

    private function editorSource(string $blockName, string $title, string $textDomain): string
    {
        return sprintf(
            <<<'TS'
import { useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockType('%s', {
    title: __('%s', '%s'),
    edit: () => {
        const blockProps = useBlockProps();

        return (
            <div {...blockProps}>
                <strong>{__('%s', '%s')}</strong>
            </div>
        );
    },
    save: () => null,
});
TS,
            $blockName,
            $title,
            $textDomain,
            $title,
            $textDomain,
        ) . "\n";
    }

    private function frontendSource(string $blockName): string
    {
        return sprintf("document.querySelectorAll('[data-block=\"%s\"]');\n", $blockName);
    }
}
