<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Tests\Fixtures;

use SymPress\MakerBundle\Composer\ComposerPackageLocatorInterface;

final readonly class ArrayComposerPackageLocator implements ComposerPackageLocatorInterface
{
    /** @param array<string, string> $packagePaths */
    public function __construct(
        private array $packagePaths,
    ) {
    }

    public function packagePaths(): iterable
    {
        yield from $this->packagePaths;
    }
}
