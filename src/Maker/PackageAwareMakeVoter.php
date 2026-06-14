<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use SymPress\MakerBundle\Util\PackageAwareClassDataFactory;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class PackageAwareMakeVoter extends AbstractMaker
{
    public function __construct(
        private readonly PackageAwareClassDataFactory $classDataFactory,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:voter';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new security voter class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'The name of the security voter class (e.g. <fg=yellow>BlogPostVoter</>)',
            )
            ->setHelp($this->getHelpFileContents('MakeVoter.txt'));
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $voterClassData = ClassData::create(
            class: $this->classDataFactory->classFromInput(
                (string) $input->getArgument('name'),
                'Security\\Voter',
            ),
            suffix: 'Voter',
            extendsClass: Voter::class,
            useStatements: [
                TokenInterface::class,
                Voter::class,
                UserInterface::class,
                Vote::class,
            ],
        );

        $generator->generateClassFromClassData(
            $voterClassData,
            'security/Voter.tpl.php',
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Open your voter and add your logic.',
            'Find the documentation at <fg=yellow>https://symfony.com/doc/current/security/voters.html</>',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            Voter::class,
            'security',
        );
    }
}
