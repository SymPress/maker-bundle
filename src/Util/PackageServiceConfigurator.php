<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

final class PackageServiceConfigurator
{
    /** @param list<array<string, mixed>> $tags */
    public function hook(
        PackageContext $context,
        string $className,
        array $tags,
    ): ?FileUpdate {

        return $this->services(
            $context,
            [
                [
                    'class' => $className,
                    'yaml'  => $this->yamlTags($tags),
                    'php'   => $this->phpTags($tags),
                ],
            ],
        );
    }

    public function block(
        PackageContext $context,
        string $className,
        bool $localizable,
    ): ?FileUpdate {

        return $this->blockWithAssetLoaders($context, $className, $localizable, []);
    }

    /** @param list<string> $assetLoaderClasses */
    public function blockWithAssetLoaders(
        PackageContext $context,
        string $className,
        bool $localizable,
        array $assetLoaderClasses,
    ): ?FileUpdate {

        $tags = [$context->tagPrefix() . '.block'];

        if ($localizable) {
            $tags[] = $context->tagPrefix() . '.localizable_block';
        }

        $definitions = [
            [
                'class' => $className,
                'yaml'  => $this->yamlSimpleTags($tags),
                'php'   => $this->phpSimpleTags($tags),
            ],
        ];

        foreach ($assetLoaderClasses as $assetLoaderClass) {
            $definitions[] = [
                'class' => $assetLoaderClass,
                'yaml'  => $this->yamlSimpleTags([$context->tagPrefix() . '.asset_loader']),
                'php'   => $this->phpSimpleTags([$context->tagPrefix() . '.asset_loader']),
            ];
        }

        return $this->services(
            $context,
            $definitions,
        );
    }

    public function assetLoader(
        PackageContext $context,
        string $className,
    ): ?FileUpdate {

        return $this->services(
            $context,
            [
                [
                    'class' => $className,
                    'yaml'  => $this->yamlSimpleTags([$context->tagPrefix() . '.asset_loader']),
                    'php'   => $this->phpSimpleTags([$context->tagPrefix() . '.asset_loader']),
                ],
            ],
        );
    }

    public function configLoaders(
        PackageContext $context,
        string $frontendClass,
        string $gutenbergClass,
    ): ?FileUpdate {

        return $this->services(
            $context,
            [
                [
                    'class' => $gutenbergClass,
                    'yaml'  => [
                        '        arguments:',
                        sprintf(
                            '            $blocks: !tagged_iterator %s.localizable_block',
                            $context->tagPrefix(),
                        ),
                        sprintf('        tags: [\'%s.asset_loader\']', $context->tagPrefix()),
                    ],
                    'php'   => [
                        sprintf(
                            '        ->arg(\'$blocks\', tagged_iterator(\'%s.localizable_block\'))',
                            $context->tagPrefix(),
                        ),
                        sprintf('        ->tag(\'%s.asset_loader\')', $context->tagPrefix()),
                    ],
                ],
                [
                    'class' => $frontendClass,
                    'yaml'  => $this->yamlSimpleTags([$context->tagPrefix() . '.asset_loader']),
                    'php'   => $this->phpSimpleTags([$context->tagPrefix() . '.asset_loader']),
                ],
            ],
        );
    }

    public function simple(PackageContext $context, string $className): ?FileUpdate
    {
        return $this->services(
            $context,
            [
                [
                    'class' => $className,
                    'yaml'  => ['        ~'],
                    'php'   => [],
                ],
            ],
        );
    }

    /** @param list<array{class: string, yaml: list<string>, php: list<string>}> $definitions */
    private function services(PackageContext $context, array $definitions): ?FileUpdate
    {
        $file = $this->serviceFile($context);
        $extension = pathinfo($file, \PATHINFO_EXTENSION);
        $contents = is_file($file) ? file_get_contents($file) : null;
        $contents = is_string($contents) && $contents !== ''
            ? $contents
            : $this->defaultContents($extension);
        $updated = $contents;

        foreach ($definitions as $definition) {
            $updated = $extension === 'php'
                ? $this->appendPhpDefinition($updated, $definition['class'], $definition['php'])
                : $this->appendYamlDefinition($updated, $definition['class'], $definition['yaml']);
        }

        if ($updated === $contents) {
            return null;
        }

        return new FileUpdate($context->relativePath($file), $updated);
    }

    private function serviceFile(PackageContext $context): string
    {
        foreach (['services.yaml', 'services.yml', 'services.php'] as $file) {
            $path = sprintf('%s/Resources/config/%s', $context->packagePath, $file);

            if (is_file($path)) {
                return $path;
            }
        }

        return sprintf('%s/Resources/config/services.php', $context->packagePath);
    }

    private function defaultContents(string $extension): string
    {
        if ($extension === 'php') {
            return <<<'PHP'
<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();
};
PHP . "\n";
        }

        return <<<'YAML'
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: false
YAML . "\n";
    }

    /** @param list<string> $body */
    private function appendYamlDefinition(string $contents, string $className, array $body): string
    {
        if (str_contains($contents, sprintf("\n    %s:", $className))) {
            return $contents;
        }

        return rtrim($contents) . "\n\n    {$className}:\n" . implode("\n", $body) . "\n";
    }

    /** @param list<string> $body */
    private function appendPhpDefinition(string $contents, string $className, array $body): string
    {
        if (str_contains($contents, sprintf('\\%s::class', $className))) {
            return $contents;
        }

        $lines = [sprintf('    $services->set(\\%s::class)', $className)];

        foreach ($body as $line) {
            $lines[] = $line;
        }

        $definition = implode("\n", $lines) . ";\n";
        $position = strrpos($contents, '};');

        if ($position === false) {
            return rtrim($contents) . "\n\n" . $definition;
        }

        return rtrim(substr($contents, 0, $position)) . "\n\n" . $definition . substr($contents, $position);
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private function yamlSimpleTags(array $tags): array
    {
        if (count($tags) === 1) {
            return [sprintf('        tags: [\'%s\']', $this->yamlQuote($tags[0]))];
        }

        return [
            '        tags:',
            ...array_map(
                fn (string $tag): string => sprintf('            - %s', $this->yamlQuote($tag)),
                $tags,
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $tags
     * @return list<string>
     */
    private function yamlTags(array $tags): array
    {
        return [
            '        tags:',
            ...array_map(fn (array $tag): string => '            - ' . $this->yamlInlineMap($tag), $tags),
        ];
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private function phpSimpleTags(array $tags): array
    {
        return array_map(static fn (string $tag): string => sprintf('        ->tag(\'%s\')', $tag), $tags);
    }

    /**
     * @param list<array<string, mixed>> $tags
     * @return list<string>
     */
    private function phpTags(array $tags): array
    {
        return array_map(
            fn (array $tag): string => sprintf('        ->tag(\'%s\', %s)', $tag['name'], $this->phpArray($tag)),
            $tags,
        );
    }

    /** @param array<string, mixed> $values */
    private function yamlInlineMap(array $values): string
    {
        $parts = [];

        foreach ($values as $key => $value) {
            $parts[] = sprintf('%s: %s', $key, is_int($value) ? (string) $value : $this->yamlQuote((string) $value));
        }

        return '{ ' . implode(', ', $parts) . ' }';
    }

    /** @param array<string, mixed> $values */
    private function phpArray(array $values): string
    {
        unset($values['name']);
        $parts = [];

        foreach ($values as $key => $value) {
            $parts[] = sprintf(
                '\'%s\' => %s',
                $key,
                is_int($value) ? (string) $value : sprintf('\'%s\'', str_replace('\'', '\\\'', (string) $value)),
            );
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function yamlQuote(string $value): string
    {
        return '\'' . str_replace('\'', '\'\'', $value) . '\'';
    }
}
