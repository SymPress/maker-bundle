<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Maker;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Maker\MakeAssetEntry;
use SymPress\MakerBundle\Maker\MakeBlock;
use SymPress\MakerBundle\Maker\MakeConfigLoader;
use SymPress\MakerBundle\Maker\MakeDataProvider;
use SymPress\MakerBundle\Maker\MakeHook;
use SymPress\MakerBundle\Maker\MakeSympressPackage;
use SymPress\MakerBundle\Maker\PackageAwareMakeController;
use SymPress\MakerBundle\Maker\PackageAwareMakeValidator;
use SymPress\MakerBundle\Maker\PackageAwareMakeVoter;

final class MakerContractTest extends TestCase
{
    public function testDocumentedMakerSkeletonsExist(): void
    {
        $matrix = (string) file_get_contents(__DIR__ . '/../../docs/maker-matrix.md');
        $contracts = [
            PackageAwareMakeController::class => [],
            PackageAwareMakeValidator::class  => [],
            PackageAwareMakeVoter::class      => [],
            MakeSympressPackage::class        => [],
            MakeHook::class                   => ['hook/Hook.tpl.php'],
            MakeBlock::class                  => [
                'block/Block.tpl.php',
                'config_loader/ConfigLoaderInterface.tpl.php',
                'asset_entry/AssetConfigLoader.tpl.php',
            ],
            MakeConfigLoader::class           => [
                'config_loader/ConfigLoaderInterface.tpl.php',
                'config_loader/FrontendConfigLoader.tpl.php',
                'config_loader/GutenbergConfigLoader.tpl.php',
            ],
            MakeAssetEntry::class             => [
                'config_loader/ConfigLoaderInterface.tpl.php',
                'asset_entry/AssetConfigLoader.tpl.php',
            ],
            MakeDataProvider::class           => ['data_provider/DataProvider.tpl.php'],
        ];

        foreach ($contracts as $maker => $skeletons) {
            self::assertStringContainsString('`' . $maker::getCommandName() . '`', $matrix);

            foreach ($skeletons as $skeleton) {
                $path = __DIR__ . '/../../Resources/skeleton/' . $skeleton;

                self::assertFileExists($path);
                self::assertStringContainsString('`Resources/skeleton/' . $skeleton . '`', $matrix);
            }
        }
    }
}
