<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Maker;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\SymPressMakerBundle;
use Symfony\Bundle\MakerBundle\DependencyInjection\DecoratorHelper;
use Symfony\Bundle\MakerBundle\Maker\MakeCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
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
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract()) {
                continue;
            }
            $definition->setPublic(true);
        }
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->findTaggedServiceIds('console.command') as $id => $tags) {
                    $container->getDefinition($id)->setPublic(true);
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING, 1000);
        $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);
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
        $helper = $lint->get('maker.decorator_helper');
        self::assertInstanceOf(DecoratorHelper::class, $helper);
        self::assertContains('event_dispatcher', $helper->suggestIds());
        self::assertSame('event_dispatcher', $helper->getRealId('EventDispatcher'));
        self::assertSame(EventDispatcher::class, $helper->getClass('event_dispatcher'));
        foreach ($lint->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'maker.') || str_starts_with($id, 'maker.auto_command.')) {
                continue;
            }
            $class = $definition->getClass();
            if (!is_string($class)) {
                continue;
            }
            self::assertInstanceOf($class, $lint->get($id), $id);
        }
        $application = new Application();
        $application->setAutoExit(false);
        foreach ($lint->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'maker.auto_command.')) {
                continue;
            }
            $command = $lint->get($id);
            self::assertInstanceOf(Command::class, $command, $id);
            $application->addCommand($command);
        }
        $tester = new ApplicationTester($application);
        self::assertSame(Command::SUCCESS, $tester->run(['command' => 'list', 'namespace' => 'make', '--format' => 'json']));
        $commands = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertContains('make:decorator', array_column($commands['commands'], 'name'));
    }
}
