<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Composer;

interface ComposerPackageLocatorInterface
{
    /**
     * @return iterable<string, string> Composer package name to absolute install path.
     */
    public function packagePaths(): iterable;
}
