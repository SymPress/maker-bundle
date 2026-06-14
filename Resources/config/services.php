<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SymPress\MakerBundle\Composer\ComposerPackageLocatorInterface;
use SymPress\MakerBundle\Composer\InstalledComposerPackageLocator;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use SymPress\MakerBundle\Maker\MakeAssetEntry;
use SymPress\MakerBundle\Maker\MakeBlock;
use SymPress\MakerBundle\Maker\MakeConfigLoader;
use SymPress\MakerBundle\Maker\MakeDataProvider;
use SymPress\MakerBundle\Maker\MakeHook;
use SymPress\MakerBundle\Maker\MakeSympressPackage;
use SymPress\MakerBundle\Maker\PackageAwareMakeController;
use SymPress\MakerBundle\Maker\PackageAwareMakeValidator;
use SymPress\MakerBundle\Maker\PackageAwareMakeVoter;
use SymPress\MakerBundle\Util\JsonFile;
use SymPress\MakerBundle\Util\PackageAwareClassDataFactory;
use SymPress\MakerBundle\Util\PackageAwareAutoloaderUtil;
use SymPress\MakerBundle\Util\PackageAwareGenerator;
use SymPress\MakerBundle\Util\PackageContextResolver;
use SymPress\MakerBundle\Util\PackageServiceConfigurator;
use SymPress\MakerBundle\Util\WebpackEncoreEntryConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(InstalledComposerPackageLocator::class)
        ->args([
            '%kernel.project_dir%',
            '%kernel.package_prefixes%',
        ]);

    $services->alias(ComposerPackageLocatorInterface::class, InstalledComposerPackageLocator::class);

    $services->set(PackageAutoloadResolver::class)
        ->args([service(ComposerPackageLocatorInterface::class)])
        ->public();

    $services->set(PackageAwareClassDataFactory::class)
        ->args([service(PackageAutoloadResolver::class)]);

    $services->set(JsonFile::class);

    $services->set(PackageContextResolver::class)
        ->args([
            '%kernel.project_dir%',
            service(ComposerPackageLocatorInterface::class),
            service(PackageAutoloadResolver::class),
        ]);

    $services->set(PackageServiceConfigurator::class);

    $services->set(WebpackEncoreEntryConfigurator::class);

    $services->set(PackageAwareAutoloaderUtil::class)
        ->args([service(PackageAutoloadResolver::class)]);

    $services->set('maker.autoloader_util', PackageAwareAutoloaderUtil::class)
        ->args([service(PackageAutoloadResolver::class)]);

    $services->set('maker.generator', PackageAwareGenerator::class)
        ->args([
            service('maker.file_manager'),
            '',
            service(PackageAutoloadResolver::class),
            null,
            service('maker.template_component_generator'),
        ]);

    $services->set('maker.maker.make_controller', PackageAwareMakeController::class)
        ->args([service(PackageAwareClassDataFactory::class)])
        ->tag('maker.command');

    $services->set('maker.maker.make_validator', PackageAwareMakeValidator::class)
        ->args([service(PackageAwareClassDataFactory::class)])
        ->tag('maker.command');

    $services->set('maker.maker.make_voter', PackageAwareMakeVoter::class)
        ->args([service(PackageAwareClassDataFactory::class)])
        ->tag('maker.command');

    $services->set('maker.maker.make_sympress_package', MakeSympressPackage::class)
        ->args([
            '%kernel.project_dir%',
            service(JsonFile::class),
        ])
        ->tag('maker.command');

    $services->set('maker.maker.make_hook', MakeHook::class)
        ->args([
            service(PackageContextResolver::class),
            service(PackageServiceConfigurator::class),
        ])
        ->tag('maker.command');

    $services->set('maker.maker.make_block', MakeBlock::class)
        ->args([
            service(PackageContextResolver::class),
            service(PackageServiceConfigurator::class),
            service(WebpackEncoreEntryConfigurator::class),
            service(JsonFile::class),
        ])
        ->tag('maker.command');

    $services->set('maker.maker.make_config_loader', MakeConfigLoader::class)
        ->args([
            service(PackageContextResolver::class),
            service(PackageServiceConfigurator::class),
        ])
        ->tag('maker.command');

    $services->set('maker.maker.make_asset_entry', MakeAssetEntry::class)
        ->args([
            service(PackageContextResolver::class),
            service(PackageServiceConfigurator::class),
            service(WebpackEncoreEntryConfigurator::class),
        ])
        ->tag('maker.command');

    $services->set('maker.maker.make_data_provider', MakeDataProvider::class)
        ->args([
            service(PackageContextResolver::class),
            service(PackageServiceConfigurator::class),
        ])
        ->tag('maker.command');
};
