<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Maker;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\SymPressMakerBundle;
use Symfony\Bundle\MakerBundle\Maker\MakeCommand;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

final class MakerContainerTest extends TestCase
{
    public function testCompiledMakerDefinitionsSurviveDebugLintRebuild(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.package_prefixes', []);
        $container->setParameter('twig.default_path', null);
        $container->register('filesystem', Filesystem::class);
        $container->register('event_dispatcher', EventDispatcher::class);
        $bundle = new SymPressMakerBundle();
        $bundle->build($container);
        $container->getExtension('sympress_maker')->load([['root_namespace' => 'App']], $container);
        $container->getDefinition('maker.maker.make_command')->setPublic(true);
        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);
        $container->compile();
        $dump = new PhpDumper($container)->dump(['class' => 'MakerContainerRegression']);
        self::assertIsString($dump);
        self::assertArrayNotHasKey('fileManager', $container->getDefinition('maker.maker.make_command')->getArguments());

        // Debug/lint reconstructs definitions in a fresh builder; normalized bare named keys fail there.
        $lint = new ContainerBuilder();
        $lint->getParameterBag()->add($container->getParameterBag()->all());
        $lint->setDefinitions($container->getDefinitions());
        $lint->setAliases($container->getAliases());
        $lint->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);
        $lint->compile();
        self::assertInstanceOf(MakeCommand::class, $lint->get('maker.maker.make_command'));
    }
}
