<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

use SymPress\MakerBundle\Composer\PackageAutoloadResolver;
use Symfony\Bundle\MakerBundle\Util\AutoloaderUtil;

final class PackageAwareAutoloaderUtil extends AutoloaderUtil
{
    public function __construct(
        private readonly PackageAutoloadResolver $resolver,
    ) {
    }

    public function getPathForFutureClass(string $className): ?string
    {
        return $this->resolver->getPathForFutureClass($className);
    }

    public function getNamespacePrefixForClass(string $className): string
    {
        return $this->resolver->getNamespacePrefixForClass($className);
    }

    public function isNamespaceConfiguredToAutoload(string $namespace): bool
    {
        return $this->resolver->isNamespaceConfiguredToAutoload($namespace);
    }
}
