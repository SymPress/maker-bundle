<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use SymPress\MakerBundle\Util\PackageAwareClassDataFactory;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

final class PackageAwareMakeValidator extends AbstractMaker
{
    public function __construct(
        private readonly PackageAwareClassDataFactory $classDataFactory,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:validator';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new validator and constraint class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'The name of the validator class (e.g. <fg=yellow>EnabledValidator</>)',
            )
            ->setHelp($this->getHelpFileContents('MakeValidator.txt'));
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $validatorClass = $this->classDataFactory->classFromInput(
            (string) $input->getArgument('name'),
            'Validator',
        );
        $validatorClassData = ClassData::create(
            class: $validatorClass,
            suffix: 'Validator',
            extendsClass: ConstraintValidator::class,
            useStatements: [
                Constraint::class,
            ],
        );
        $constraintClassData = ClassData::create(
            class: $this->constraintClass($validatorClass, $validatorClassData),
            extendsClass: Constraint::class,
        );

        $generator->generateClassFromClassData(
            $validatorClassData,
            'validator/Validator.tpl.php',
            [
                'constraint_class_name' => $constraintClassData->getClassName(),
            ],
        );

        $generator->generateClassFromClassData(
            $constraintClassData,
            'validator/Constraint.tpl.php',
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Open your new constraint & validators and add your logic.',
            'Find the documentation at <fg=yellow>http://symfony.com/doc/current/validation/custom_constraint.html</>',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            Validation::class,
            'validator',
        );
    }

    private function constraintClass(string $validatorClass, ClassData $validatorClassData): string
    {
        if ($this->classDataFactory->isAbsoluteClass($validatorClass)) {
            return '\\' . Str::removeSuffix($validatorClassData->getFullClassName(), 'Validator');
        }

        return sprintf(
            'Validator\\%s',
            Str::removeSuffix($validatorClassData->getClassName(), 'Validator'),
        );
    }
}
