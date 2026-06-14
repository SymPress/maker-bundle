<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Maker;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Util\PackageAwareClassDataFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackageAwareMakeController extends AbstractMaker
{
    private bool $isInvokable = false;
    private ?ClassData $controllerClassData = null;
    private bool $usesTwigTemplate = false;
    private string $twigTemplatePath = '';
    private string $relativeControllerName = '';
    private ?string $packageRootNamespace = null;
    private bool $generateTests = false;

    public function __construct(
        private readonly PackageAwareClassDataFactory $classDataFactory,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:controller';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new controller class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument(
                'controller-class',
                InputArgument::OPTIONAL,
                sprintf(
                    'Choose a name for your controller class (e.g. <fg=yellow>%sController</>)',
                    Str::asClassName(Str::getRandomTerm()),
                ),
            )
            ->addOption('no-template', null, InputOption::VALUE_NONE, 'Use this option to disable template generation')
            ->addOption('invokable', null, InputOption::VALUE_NONE, 'Use this option to create an invokable controller')
            ->addOption('with-tests', null, InputOption::VALUE_NONE, 'Generate PHPUnit Tests')
            ->setHelp($this->getHelpFileContents('MakeController.txt') . "\n" . $this->getHelpFileContents('_WithTests.txt'));
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $this->configureState($input);
        $this->generateTests = (bool) $input->getOption('with-tests');

        if ($this->generateTests) {
            return;
        }

        $this->generateTests = $io->confirm('Do you want to generate PHPUnit tests? [Experimental]', false);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        if ($this->controllerClassData === null) {
            $this->configureState($input);
            $this->generateTests = (bool) $input->getOption('with-tests');
        }

        $controllerPath = $generator->generateClassFromClassData(
            $this->controllerClassData,
            'controller/Controller.tpl.php',
            [
                'route_path'    => Str::asRoutePath($this->relativeControllerName),
                'route_name'    => Str::asRouteName($this->relativeControllerName),
                'method_name'   => $this->isInvokable ? '__invoke' : 'index',
                'with_template' => $this->usesTwigTemplate,
                'template_name' => $this->twigTemplatePath,
            ],
            true,
        );

        if ($this->usesTwigTemplate) {
            $generator->generateTemplate(
                $this->twigTemplatePath,
                'controller/twig_template.tpl.php',
                [
                    'controller_path' => $controllerPath,
                    'root_directory'  => $generator->getRootDirectory(),
                    'class_name'      => $this->controllerClassData->getClassName(),
                ],
            );
        }

        if ($this->generateTests) {
            $testClassData = ClassData::create(
                class: $this->testClassName(),
                suffix: 'ControllerTest',
                extendsClass: WebTestCase::class,
            );

            $generator->generateClassFromClassData(
                $testClassData,
                'controller/test/Test.tpl.php',
                [
                    'route_path' => Str::asRoutePath($this->relativeControllerName),
                ],
            );

            if (!class_exists(TestCase::class)) {
                $io->caution('You\'ll need to install the `symfony/test-pack` to execute the tests for your new controller.');
            }
        }

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text('Next: Open your new controller class and add some pages!');
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function configureState(InputInterface $input): void
    {
        $this->usesTwigTemplate = $this->isTwigInstalled() && !$input->getOption('no-template');
        $this->isInvokable = (bool) $input->getOption('invokable');

        $controllerClass = (string) $input->getArgument('controller-class');
        $controllerClassName = $this->classDataFactory->classFromInput($controllerClass, 'Controller');
        $isAbsoluteNamespace = $this->classDataFactory->isAbsoluteClass($controllerClassName);

        $this->controllerClassData = ClassData::create(
            class: $controllerClassName,
            suffix: 'Controller',
            extendsClass: AbstractController::class,
            useStatements: [
                $this->usesTwigTemplate ? Response::class : JsonResponse::class,
                Route::class,
            ],
        );
        $this->packageRootNamespace = $this->classDataFactory->packageRootNamespaceForClassData(
            $this->controllerClassData,
        );
        $relativeControllerName = $isAbsoluteNamespace
            ? $this->classDataFactory->relativeNameFromAbsoluteClassData($this->controllerClassData, true)
            : $this->controllerClassData->getClassName(relative: true, withoutSuffix: true);

        $this->relativeControllerName = $this->packageRootNamespace === null
            ? $relativeControllerName
            : $this->stripLeadingNamespace($relativeControllerName, 'Controller');
        $this->twigTemplatePath = sprintf(
            '%s%s',
            Str::asFilePath($this->relativeControllerName),
            $this->isInvokable ? '.html.twig' : '/index.html.twig',
        );
    }

    private function testClassName(): string
    {
        if (
            $this->packageRootNamespace !== null
            && $this->classDataFactory->isNamespaceConfiguredToAutoload($this->packageRootNamespace . '\\Tests')
        ) {
            return sprintf(
                '\\%s\\Tests\\Controller\\%s',
                $this->packageRootNamespace,
                $this->stripLeadingNamespace($this->relativeControllerName, 'Controller'),
            );
        }

        return sprintf('Tests\Controller\%s', $this->relativeControllerName);
    }

    private function stripLeadingNamespace(string $className, string $namespace): string
    {
        $prefix = trim($namespace, '\\') . '\\';

        if (!str_starts_with($className, $prefix)) {
            return $className;
        }

        return substr($className, strlen($prefix));
    }

    private function isTwigInstalled(): bool
    {
        return class_exists(TwigBundle::class);
    }
}
