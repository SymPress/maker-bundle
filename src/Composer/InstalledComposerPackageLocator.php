<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Composer;

use Composer\InstalledVersions;

final readonly class InstalledComposerPackageLocator implements ComposerPackageLocatorInterface
{
    /** @param list<string>|array<string> $packagePrefixes */
    public function __construct(
        private string $projectDir,
        private array $packagePrefixes = [],
    ) {
    }

    public function packagePaths(): iterable
    {
        $seen = [];

        yield '__root__' => rtrim($this->projectDir, '/');
        $seen['__root__'] = true;

        foreach ($this->localPackagePaths($seen) as $packageName => $path) {
            yield $packageName => $path;
            $seen[$packageName] = true;
        }

        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            if (isset($seen[$packageName])) {
                continue;
            }

            if (!$this->matchesPackagePrefix($packageName)) {
                continue;
            }

            $path = InstalledVersions::getInstallPath($packageName);

            if (!is_string($path) || $path === '') {
                continue;
            }

            yield $packageName => rtrim($path, '/');
            $seen[$packageName] = true;
        }
    }

    private function matchesPackagePrefix(string $packageName): bool
    {
        if ($this->packagePrefixes === []) {
            return true;
        }

        foreach ($this->normalizedPackagePrefixes() as $prefix) {
            if (str_starts_with($packageName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $seen
     * @return iterable<string, string>
     */
    private function localPackagePaths(array $seen): iterable
    {
        $packageFiles = glob(sprintf('%s/packages/*/composer.json', rtrim($this->projectDir, '/'))) ?: [];
        sort($packageFiles);

        foreach ($packageFiles as $composerFile) {
            $metadata = $this->composerMetadata($composerFile);
            $packageName = (string) ($metadata['name'] ?? '');

            if ($packageName === '' || isset($seen[$packageName]) || !$this->matchesPackagePrefix($packageName)) {
                continue;
            }

            yield $packageName => dirname($composerFile);
        }
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

    /** @return list<string> */
    private function normalizedPackagePrefixes(): array
    {
        $normalized = [];

        foreach ($this->packagePrefixes as $prefix) {
            if (!is_string($prefix)) {
                continue;
            }

            $prefix = trim($prefix);

            if ($prefix === '') {
                continue;
            }

            $normalized[] = str_ends_with($prefix, '/') ? $prefix : "{$prefix}/";
        }

        return array_values(array_unique($normalized));
    }
}
