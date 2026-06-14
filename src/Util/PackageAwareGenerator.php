<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Bundle\MakerBundle\Util\PhpCompatUtil;
use Symfony\Bundle\MakerBundle\Util\TemplateComponentGenerator;

final class PackageAwareGenerator extends Generator
{
    private ?string $packageRootNamespace = null;

    public function __construct(
        FileManager $fileManager,
        string $namespacePrefix,
        private readonly PackageAutoloadResolver $resolver,
        ?PhpCompatUtil $phpCompatUtil = null,
        ?TemplateComponentGenerator $templateComponentGenerator = null,
    ) {
        parent::__construct($fileManager, $namespacePrefix, $phpCompatUtil, $templateComponentGenerator);
    }

    public function generateClass(string $className, string $templateName, array $variables = []): string
    {
        if (isset($variables['class_data']) && $variables['class_data'] instanceof ClassData) {
            $this->normalizeClassData($variables['class_data']);
            $className = $variables['class_data']->getFullClassName();
        } else {
            $className = $this->resolver->absolutePackageClassName($className) ?? $className;
        }

        return parent::generateClass($className, $templateName, $variables);
    }

    public function createClassNameDetails(
        string $name,
        string $namespacePrefix,
        string $suffix = '',
        string $validationErrorMessage = '',
    ): ClassNameDetails {

        return parent::createClassNameDetails(
            $this->packageClassName($name, $namespacePrefix, $suffix),
            $namespacePrefix,
            $suffix,
            $validationErrorMessage,
        );
    }

    private function packageClassName(string $name, string $namespacePrefix, string $suffix): string
    {
        if ($name === '' || !str_contains($name, '\\')) {
            return $this->relativePackageClassName($name, $namespacePrefix, $suffix);
        }

        $className = $this->resolver->absolutePackageClassName($name);

        if ($className === null) {
            return $this->relativePackageClassName($name, $namespacePrefix, $suffix);
        }

        $this->packageRootNamespace = $this->resolver->packageRootNamespaceForClass($className);

        return '\\' . $this->addSuffix($className, $suffix);
    }

    private function relativePackageClassName(string $name, string $namespacePrefix, string $suffix): string
    {
        if ($name === '' || $name[0] === '\\' || $this->packageRootNamespace === null) {
            return $name;
        }

        $namespacePrefix = trim($namespacePrefix, '\\');
        $parts = [$this->packageRootNamespace];

        if ($namespacePrefix !== '') {
            $parts[] = $namespacePrefix;
        }

        $parts[] = Str::asClassName($name, $suffix);

        return '\\' . implode('\\', $parts);
    }

    private function addSuffix(string $className, string $suffix): string
    {
        if ($suffix === '') {
            return $className;
        }

        $namespace = Str::getNamespace($className);
        $shortName = Str::asClassName(Str::getShortClassName($className), $suffix);

        return $namespace === '' ? $shortName : sprintf('%s\\%s', $namespace, $shortName);
    }

    private function normalizeClassData(ClassData $classData): void
    {
        $className = $this->resolver->absolutePackageClassName($classData->getFullClassName());

        if ($className === null) {
            return;
        }

        $reflection = new \ReflectionClass($classData);
        $namespace = Str::getNamespace($className);

        $reflection->getProperty('className')->setValue($classData, Str::getShortClassName($className));
        $reflection->getProperty('namespace')->setValue(
            $classData,
            $namespace === '' ? '' : '\\' . $namespace,
        );
    }
}
