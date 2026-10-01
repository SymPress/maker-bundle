<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

use Symfony\Component\Filesystem\Path;

final class ProjectPath
{
    public static function relative(string $project, string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('~(?:^|/)\.\.(?:/|$)~', $path) === 1) {
            throw new \InvalidArgumentException('Package output cannot contain traversal segments.');
        }
        if ($path === '' || str_contains($path, "\0") || Path::isAbsolute($path)) {
            throw new \InvalidArgumentException('Package output must be a relative path within the project.');
        }
        $root = realpath($project);
        if ($root === false) {
            throw new \InvalidArgumentException('Project directory does not exist.');
        }
        $absolute = Path::makeAbsolute($path, $root);
        $ancestor = $absolute;
        while (!file_exists($ancestor) && !is_link($ancestor)) {
            $ancestor = dirname($ancestor);
        }
        $real = realpath($ancestor);
        if ($real === false || ($real !== $root && !str_starts_with($real, $root . '/')) || !str_starts_with($absolute, $root . '/')) {
            throw new \InvalidArgumentException('Package output escapes the project directory.');
        }
        return Path::makeRelative($absolute, $root);
    }
}
