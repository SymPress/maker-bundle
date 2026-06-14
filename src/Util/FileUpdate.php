<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

final readonly class FileUpdate
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {
    }
}
