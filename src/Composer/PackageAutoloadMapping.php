<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Composer;

final readonly class PackageAutoloadMapping
{
    public function __construct(
        public string $package,
        public string $namespacePrefix,
        public string $path,
        public bool $dev,
    ) {
    }

    public function pathForClass(string $className): ?string
    {
        if (!str_starts_with($className, $this->namespacePrefix)) {
            return null;
        }

        $relativeClass = substr($className, strlen($this->namespacePrefix));

        if ($relativeClass === '') {
            return null;
        }

        return sprintf(
            '%s/%s.php',
            rtrim($this->path, '/'),
            str_replace('\\', '/', $relativeClass),
        );
    }

    public function containsOrExtendsNamespace(string $namespace): bool
    {
        $namespace = trim($namespace, '\\');
        $prefix = trim($this->namespacePrefix, '\\');

        if ($namespace === '' || $prefix === '') {
            return false;
        }

        return str_starts_with("{$namespace}\\", "{$prefix}\\")
            || str_starts_with("{$prefix}\\", "{$namespace}\\");
    }
}
