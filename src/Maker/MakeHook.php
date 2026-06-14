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

final class MakeHook extends AbstractMaker
{
    public function __construct(
        private readonly PackageContextResolver $contextResolver,
        private readonly PackageServiceConfigurator $serviceConfigurator,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:hook';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a package-aware WordPress hook service';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('class', InputArgument::REQUIRED, 'Hook class name or FQCN')
            ->addOption('hook', null, InputOption::VALUE_REQUIRED, 'WordPress hook name', 'init')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Service method called by the hook', 'register')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Hook type: action or filter', 'action')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Hook priority', 10)
            ->addOption('accepted-args', null, InputOption::VALUE_REQUIRED, 'Accepted hook arguments', 1)
            ->addOption('no-service', null, InputOption::VALUE_NONE, 'Do not update the package service config');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $type = (string) $input->getOption('type');

        if (!in_array($type, ['action', 'filter'], true)) {
            throw new \InvalidArgumentException('Hook type must be "action" or "filter".');
        }

        $method = (string) $input->getOption('method');
        $classDetails = $generator->createClassNameDetails((string) $input->getArgument('class'), 'Hook\\');
        $generator->generateClass(
            $classDetails->getFullName(),
            $this->template(),
            [
                'method_name' => $method,
                'type'        => $type,
            ],
        );

        if (!$input->getOption('no-service')) {
            $context = $this->contextResolver->fromClassName($classDetails->getFullName());
            $tag = [
                'name'   => 'kernel.hook',
                'hook'   => (string) $input->getOption('hook'),
                'method' => $method,
            ];

            if ($type === 'filter') {
                $tag['type'] = 'filter';
            }

            $priority = (int) $input->getOption('priority');
            $acceptedArgs = (int) $input->getOption('accepted-args');

            if ($priority !== 10) {
                $tag['priority'] = $priority;
            }

            if ($acceptedArgs !== 1) {
                $tag['accepted_args'] = $acceptedArgs;
            }

            $update = $this->serviceConfigurator->hook($context, $classDetails->getFullName(), [$tag]);

            if ($update !== null) {
                $generator->dumpFile($update->path, $update->contents);
            }
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text(sprintf('Hook service "%s" was created.', $classDetails->getFullName()));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function template(): string
    {
        return __DIR__ . '/../../Resources/skeleton/hook/Hook.tpl.php';
    }
}
