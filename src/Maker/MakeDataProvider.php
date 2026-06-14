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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class MakeDataProvider extends AbstractMaker
{
    public function __construct(
        private readonly PackageContextResolver $contextResolver,
        private readonly PackageServiceConfigurator $serviceConfigurator,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:data-provider';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a templating data provider in a package';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('class', InputArgument::REQUIRED, 'Data provider class name or FQCN')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Provider method name', 'provide')
            ->addOption('no-service', null, InputOption::VALUE_NONE, 'Do not update the package service config');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $classDetails = $generator->createClassNameDetails(
            (string) $input->getArgument('class'),
            'DataProvider\\Templating\\',
            'DataProvider',
        );

        $generator->generateClass(
            $classDetails->getFullName(),
            __DIR__ . '/../../Resources/skeleton/data_provider/DataProvider.tpl.php',
            [
                'method_name' => (string) $input->getOption('method'),
            ],
        );

        if (!$input->getOption('no-service')) {
            $context = $this->contextResolver->fromClassName($classDetails->getFullName());
            $update = $this->serviceConfigurator->simple($context, $classDetails->getFullName());

            if ($update !== null) {
                $generator->dumpFile($update->path, $update->contents);
            }
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text(sprintf('Data provider "%s" was created.', $classDetails->getFullName()));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
