<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

use Symfony\Bundle\MakerBundle\Str;

final readonly class PackageContext
{
    public function __construct(
        public string $projectDir,
        public string $packageName,
        public string $packagePath,
        public string $namespacePrefix,
    ) {
    }

    public function rootNamespace(): string
    {
        return trim($this->namespacePrefix, '\\');
    }

    public function directoryName(): string
    {
        return basename($this->packagePath);
    }

    public function shortName(): string
    {
        $position = strpos($this->packageName, '/');

        return $position === false ? $this->packageName : substr($this->packageName, $position + 1);
    }

    public function className(string $relativeClassName): string
    {
        return sprintf(
            '%s\\%s',
            $this->rootNamespace(),
            trim($relativeClassName, '\\'),
        );
    }

    public function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $projectDir = rtrim(str_replace('\\', '/', $this->projectDir), '/');

        if (str_starts_with($path, $projectDir . '/')) {
            return substr($path, strlen($projectDir) + 1);
        }

        return ltrim($path, '/');
    }

    public function packageRelativePath(string $path): string
    {
        return sprintf(
            '%s/%s',
            $this->relativePath($this->packagePath),
            ltrim(str_replace('\\', '/', $path), '/'),
        );
    }

    public function tagPrefix(): string
    {
        return $this->normalizeHandle($this->shortName());
    }

    public function assetHandlePrefix(): string
    {
        return $this->normalizeHandle($this->directoryName());
    }

    public function textDomain(): string
    {
        return $this->normalizeHandle($this->directoryName());
    }

    public function phpVariablePrefix(): string
    {
        return Str::asLowerCamelCase($this->shortName());
    }

    private function normalizeHandle(string $name): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

        return trim($normalized, '-');
    }
}
