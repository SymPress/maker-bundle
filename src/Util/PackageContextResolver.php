<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

use SymPress\MakerBundle\Composer\ComposerPackageLocatorInterface;
use SymPress\MakerBundle\Composer\PackageAutoloadMapping;
use SymPress\MakerBundle\Composer\PackageAutoloadResolver;

final class PackageContextResolver
{
    public function __construct(
        private readonly string $projectDir,
        private readonly ComposerPackageLocatorInterface $packageLocator,
        private readonly PackageAutoloadResolver $autoloadResolver,
    ) {
    }

    public function fromPackageName(string $packageName): PackageContext
    {
        $packageName = strtolower(trim($packageName));
        $paths = $this->packagePaths();

        if (!isset($paths[$packageName])) {
            throw new \RuntimeException(sprintf('Package "%s" could not be found.', $packageName));
        }

        $mapping = $this->firstPackageMapping($packageName);

        if (!$mapping instanceof PackageAutoloadMapping) {
            throw new \RuntimeException(sprintf('Package "%s" has no PSR-4 autoload namespace.', $packageName));
        }

        return $this->contextFromMapping($mapping, $paths[$packageName]);
    }

    public function fromClassName(string $className): PackageContext
    {
        $className = trim($className, '\\');

        foreach ($this->autoloadResolver->mappings() as $mapping) {
            if (!str_starts_with($className . '\\', $mapping->namespacePrefix)) {
                continue;
            }

            return $this->contextFromMapping($mapping, $this->packagePath($mapping->package));
        }

        throw new \RuntimeException(sprintf('Class "%s" does not belong to a known package namespace.', $className));
    }

    public function fromPackageOrClass(string $input): PackageContext
    {
        $input = trim($input);

        if (str_contains($input, '\\')) {
            return $this->fromClassName($input);
        }

        return $this->fromPackageName($input);
    }

    private function firstPackageMapping(string $packageName): ?PackageAutoloadMapping
    {
        foreach ($this->autoloadResolver->mappings() as $mapping) {
            if ($mapping->package === $packageName && !$mapping->dev) {
                return $mapping;
            }
        }

        foreach ($this->autoloadResolver->mappings() as $mapping) {
            if ($mapping->package === $packageName) {
                return $mapping;
            }
        }

        return null;
    }

    private function packagePath(string $packageName): string
    {
        $paths = $this->packagePaths();

        if (!isset($paths[$packageName])) {
            throw new \RuntimeException(sprintf('Package "%s" could not be found.', $packageName));
        }

        return $paths[$packageName];
    }

    private function contextFromMapping(PackageAutoloadMapping $mapping, string $packagePath): PackageContext
    {
        return new PackageContext(
            $this->projectDir,
            $mapping->package,
            rtrim($packagePath, '/'),
            trim($mapping->namespacePrefix, '\\'),
        );
    }

    /** @return array<string, string> */
    private function packagePaths(): array
    {
        return iterator_to_array($this->packageLocator->packagePaths());
    }
}
