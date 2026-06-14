<?php

declare(strict_types=1);

namespace SymPress\MakerBundle;

use SymPress\Kernel\Bundle\AbstractBundle;
use SymPress\MakerBundle\Composer\InstalledComposerPackageLocator;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\MakeCommandRegistrationPass;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\RemoveMissingParametersPass;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\SetDoctrineAnnotatedPrefixesPass;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class SymPressMakerBundle extends AbstractBundle
{
    protected string $extensionAlias = 'sympress_maker';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('root_namespace')
                    ->defaultNull()
                    ->validate()
                        ->ifString()
                        ->then(static fn (string $namespace): string => Validator::validateClassName($namespace))
                    ->end()
                ->end()
                ->scalarNode('entity_namespace')->defaultNull()->end()
                ->booleanNode('generate_final_classes')->defaultTrue()->end()
                ->booleanNode('generate_final_entities')->defaultFalse()->end()
            ->end();
    }

    public function loadExtension(
        array $config,
        ContainerConfigurator $configurator,
        ContainerBuilder $container,
    ): void {

        $makerBundlePath = $this->makerBundlePath();

        $configurator->import(sprintf('%s/config/services.php', $makerBundlePath));
        $configurator->import(sprintf('%s/config/makers.php', $makerBundlePath));
        $configurator->import('../Resources/config/services.php');

        $rootNamespace = $this->rootNamespace($config, $container);
        $entityNamespace = $this->entityNamespace($config, $rootNamespace);

        $configurator->services()
            ->get('maker.autoloader_finder')
                ->arg(0, $rootNamespace)
            ->get('maker.generator')
                ->arg(1, $rootNamespace)
            ->get('maker.doctrine_helper')
                ->arg(0, $entityNamespace)
            ->get('maker.template_component_generator')
                ->arg(0, (bool) $config['generate_final_classes'])
                ->arg(1, (bool) $config['generate_final_entities'])
                ->arg(2, $rootNamespace);

        $container->setParameter('sympress_maker.root_namespace', $rootNamespace);
        $container->setParameter('sympress_maker.entity_namespace', $entityNamespace);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container
            ->registerForAutoconfiguration(MakerInterface::class)
            ->addTag(MakeCommandRegistrationPass::MAKER_TAG);

        $container->addCompilerPass(new MakeCommandRegistrationPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $container->addCompilerPass(new RemoveMissingParametersPass());
        $container->addCompilerPass(new SetDoctrineAnnotatedPrefixesPass());
    }

    private function makerBundlePath(): string
    {
        $reflection = new \ReflectionClass(MakerBundle::class);
        $file = $reflection->getFileName();

        if (!is_string($file)) {
            throw new \RuntimeException('Unable to locate symfony/maker-bundle.');
        }

        return dirname($file, 2);
    }

    /** @param array<string, mixed> $config */
    private function rootNamespace(array $config, ContainerBuilder $container): string
    {
        $configured = $config['root_namespace'] ?? null;

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured, '\\');
        }

        return $this->defaultRootNamespace($container) ?? 'App';
    }

    /** @param array<string, mixed> $config */
    private function entityNamespace(array $config, string $rootNamespace): string
    {
        $configured = $config['entity_namespace'] ?? null;

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured, '\\');
        }

        return sprintf('%s\\Entity', $rootNamespace);
    }

    private function defaultRootNamespace(ContainerBuilder $container): ?string
    {
        $projectDir = $this->stringParameter($container, 'kernel.project_dir');
        $packagePrefixes = $container->hasParameter('kernel.package_prefixes')
            ? $container->getParameter('kernel.package_prefixes')
            : [];

        $locator = new InstalledComposerPackageLocator(
            $projectDir,
            is_array($packagePrefixes) ? $packagePrefixes : [],
        );

        return (new PackageAutoloadResolver($locator))->defaultRootNamespace();
    }

    private function stringParameter(ContainerBuilder $container, string $name): string
    {
        if (!$container->hasParameter($name)) {
            return '';
        }

        $value = $container->getParameter($name);

        return is_string($value) ? $value : '';
    }
}
