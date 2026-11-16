<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Maker;

use PHPUnit\Framework\TestCase;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use SymPress\MakerBundle\Maker\MakeDataProvider;
use SymPress\MakerBundle\Tests\Fixtures\ArrayComposerPackageLocator;
use SymPress\MakerBundle\Util\PackageAwareAutoloaderUtil;
use SymPress\MakerBundle\Util\PackageAwareGenerator;
use SymPress\MakerBundle\Util\PackageContextResolver;
use SymPress\MakerBundle\Util\PackageServiceConfigurator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\MakerFileLinkFormatter;
use Symfony\Bundle\MakerBundle\Util\TemplateComponentGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

final class MakerInputSafetyTest extends TestCase
{
    private string $workspace;
    private PackageAutoloadResolver $autoloadResolver;
    private PackageContextResolver $contextResolver;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-maker-input-' . bin2hex(random_bytes(6));
        mkdir($this->workspace . '/packages/plugin', 0777, true);
        file_put_contents(
            $this->workspace . '/packages/plugin/composer.json',
            json_encode(
                [
                    'name'     => 'site/plugin',
                    'type'     => 'wordpress-plugin',
                    'autoload' => ['psr-4' => ['Site\\Plugin\\' => 'src/']],
                ],
                \JSON_THROW_ON_ERROR,
            ),
        );
        $locator = new ArrayComposerPackageLocator(['site/plugin' => $this->workspace . '/packages/plugin']);
        $this->autoloadResolver = new PackageAutoloadResolver($locator);
        $this->contextResolver = new PackageContextResolver($this->workspace, $locator, $this->autoloadResolver);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);
    }

    public function testDataProviderRejectsMethodNameThatIsNotAnIdentifier(): void
    {
        $maker = new MakeDataProvider($this->contextResolver, new PackageServiceConfigurator());

        try {
            $this->make($maker, [
                'class'        => 'Site\\Plugin\\DataProvider\\Templating\\EvilDataProvider',
                '--method'     => "provide(): array { return []; }\n    public function __construct() { system('id'); }\n    public function x",
                '--no-service' => true,
            ]);
            self::fail('Invalid method name accepted.');
        } catch (\InvalidArgumentException) {
            self::assertDirectoryDoesNotExist($this->workspace . '/packages/plugin/src');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function make(AbstractMaker $maker, array $arguments): void
    {
        $command = new Command($maker::getCommandName());
        $maker->configureCommand($command, new InputConfiguration());
        $input = new ArrayInput($arguments, $command->getDefinition());
        $maker->generate($input, new ConsoleStyle($input, new BufferedOutput()), $this->generator());
    }

    private function generator(): PackageAwareGenerator
    {
        $fileManager = new FileManager(
            new Filesystem(),
            new PackageAwareAutoloaderUtil($this->autoloadResolver),
            new MakerFileLinkFormatter(),
            $this->workspace,
            null,
        );

        return new PackageAwareGenerator(
            $fileManager,
            'App',
            $this->autoloadResolver,
            null,
            new TemplateComponentGenerator(true, false, 'App'),
        );
    }
}
