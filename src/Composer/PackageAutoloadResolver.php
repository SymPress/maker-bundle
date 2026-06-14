<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Composer;

final class PackageAutoloadResolver
{
    /** @var list<PackageAutoloadMapping>|null */
    private ?array $mappings = null;

    public function __construct(
        private readonly ComposerPackageLocatorInterface $packageLocator,
    ) {
    }

    public function getPathForFutureClass(string $className): ?string
    {
        $className = $this->normalizeClassName($className);

        foreach ($this->mappings() as $mapping) {
            $path = $mapping->pathForClass($className);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    public function getNamespacePrefixForClass(string $className): string
    {
        $className = $this->normalizeClassName($className);

        foreach ($this->mappings() as $mapping) {
            if (str_starts_with($className, $mapping->namespacePrefix)) {
                return $mapping->namespacePrefix;
            }
        }

        return '';
    }

    public function packageRootNamespaceForClass(string $className): ?string
    {
        $prefix = $this->getNamespacePrefixForClass($className);

        return $prefix === '' ? null : trim($prefix, '\\');
    }

    public function isNamespaceConfiguredToAutoload(string $namespace): bool
    {
        foreach ($this->mappings() as $mapping) {
            if ($mapping->containsOrExtendsNamespace($namespace)) {
                return true;
            }
        }

        return false;
    }

    public function absolutePackageClassName(string $className): ?string
    {
        $className = $this->normalizeClassName($className);

        foreach ($this->mappings() as $mapping) {
            $position = strpos($className, $mapping->namespacePrefix);

            if ($position === false) {
                continue;
            }

            if ($position > 0 && $className[$position - 1] !== '\\') {
                continue;
            }

            return substr($className, $position);
        }

        return null;
    }

    private function normalizeClassName(string $className): string
    {
        $className = ltrim($className, '\\');
        $normalized = preg_replace('/\\\\+/', '\\', $className);

        return is_string($normalized) ? $normalized : $className;
    }

    public function defaultRootNamespace(): ?string
    {
        $fallback = null;

        foreach ($this->mappings() as $mapping) {
            if ($mapping->dev) {
                continue;
            }

            $packageType = $this->packageType($mapping->package);

            if ($packageType === 'wordpress-theme') {
                return trim($mapping->namespacePrefix, '\\');
            }

            $fallback ??= trim($mapping->namespacePrefix, '\\');
        }

        return $fallback;
    }

    /** @return list<PackageAutoloadMapping> */
    public function mappings(): array
    {
        if ($this->mappings !== null) {
            return $this->mappings;
        }

        $mappings = [];

        foreach ($this->packageLocator->packagePaths() as $package => $path) {
            $composerFile = sprintf('%s/composer.json', rtrim($path, '/'));

            if (!is_file($composerFile)) {
                continue;
            }

            $metadata = $this->composerMetadata($composerFile);
            $mappings = [
                ...$mappings,
                ...$this->autoloadMappings($package, $path, $metadata['autoload']['psr-4'] ?? [], false),
                ...$this->autoloadMappings($package, $path, $metadata['autoload-dev']['psr-4'] ?? [], true),
            ];
        }

        usort(
            $mappings,
            static fn (PackageAutoloadMapping $left, PackageAutoloadMapping $right): int => strlen($right->namespacePrefix) <=> strlen($left->namespacePrefix) ?: ($left->dev <=> $right->dev),
        );

        $this->mappings = $mappings;

        return $this->mappings;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<PackageAutoloadMapping>
     */
    private function autoloadMappings(
        string $package,
        string $packagePath,
        mixed $metadata,
        bool $dev,
    ): array {

        if (!is_array($metadata)) {
            return [];
        }

        $mappings = [];

        foreach ($metadata as $namespacePrefix => $paths) {
            if (!is_string($namespacePrefix) || $namespacePrefix === '') {
                continue;
            }

            $path = $this->firstPath($paths);

            if ($path === null) {
                continue;
            }

            $mappings[] = new PackageAutoloadMapping(
                $package,
                rtrim($namespacePrefix, '\\') . '\\',
                $this->absolutePath($packagePath, $path),
                $dev,
            );
        }

        return $mappings;
    }

    private function firstPath(mixed $paths): ?string
    {
        if (is_string($paths)) {
            return $paths;
        }

        if (!is_array($paths)) {
            return null;
        }

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return null;
    }

    private function absolutePath(string $packagePath, string $path): string
    {
        if ($path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1)) {
            return rtrim(str_replace('\\', '/', $path), '/');
        }

        return rtrim(sprintf('%s/%s', rtrim($packagePath, '/'), trim(str_replace('\\', '/', $path), '/')), '/');
    }

    /** @return array<string, mixed> */
    private function composerMetadata(string $composerFile): array
    {
        $contents = file_get_contents($composerFile);

        if (!is_string($contents) || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function packageType(string $package): string
    {
        foreach ($this->packageLocator->packagePaths() as $candidate => $path) {
            if ($candidate !== $package) {
                continue;
            }

            $metadata = $this->composerMetadata(sprintf('%s/composer.json', rtrim($path, '/')));

            return (string) ($metadata['type'] ?? '');
        }

        return '';
    }
}
