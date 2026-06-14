<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;

final class PackageAwareClassDataFactory
{
    public function __construct(
        private readonly PackageAutoloadResolver $resolver,
    ) {
    }

    public function classFromInput(string $name, string $defaultNamespace): string
    {
        $name = trim($name);

        if ($name !== '' && $name[0] === '\\') {
            return '\\' . trim($name, '\\');
        }

        $packageClassName = $this->resolver->absolutePackageClassName($name);

        if ($packageClassName !== null) {
            return '\\' . $packageClassName;
        }

        return sprintf('%s\\%s', trim($defaultNamespace, '\\'), $name);
    }

    public function isAbsoluteClass(string $className): bool
    {
        return str_starts_with($className, '\\');
    }

    public function relativeNameFromAbsoluteClassData(
        ClassData $classData,
        bool $withoutSuffix = false,
    ): string {

        $fullClassName = $classData->getFullClassName(false, $withoutSuffix);
        $packageRootNamespace = $this->resolver->packageRootNamespaceForClass($fullClassName);

        if ($packageRootNamespace !== null && str_starts_with($fullClassName, $packageRootNamespace . '\\')) {
            return substr($fullClassName, strlen($packageRootNamespace) + 1);
        }

        return $classData->getFullClassName(true, $withoutSuffix);
    }

    public function packageRootNamespaceForClassData(ClassData $classData): ?string
    {
        return $this->resolver->packageRootNamespaceForClass($classData->getFullClassName());
    }

    public function isNamespaceConfiguredToAutoload(string $namespace): bool
    {
        return $this->resolver->isNamespaceConfiguredToAutoload($namespace);
    }

    public function normalizeClassData(ClassData $classData): void
    {
        $packageClassName = $this->resolver->absolutePackageClassName($classData->getFullClassName());

        if ($packageClassName === null) {
            return;
        }

        $this->replaceClass($classData, $packageClassName);
    }

    private function replaceClass(ClassData $classData, string $className): void
    {
        $reflection = new \ReflectionClass($classData);
        $namespace = Str::getNamespace($className);

        $reflection->getProperty('className')->setValue($classData, Str::getShortClassName($className));
        $reflection->getProperty('namespace')->setValue(
            $classData,
            $namespace === '' ? '' : '\\' . $namespace,
        );
    }
}
