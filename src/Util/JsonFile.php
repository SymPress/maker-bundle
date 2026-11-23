<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

final class JsonFile
{
    /** @return array<string, mixed> */
    public function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $data = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('JSON file "%s" must contain an object.', $file));
        }

        return $data;
    }

    /**
     * Decodes into stdClass so empty objects ({}) survive a rewrite instead of becoming [].
     */
    public function readObject(string $file): \stdClass
    {
        $contents = is_file($file) ? file_get_contents($file) : '';

        if (!is_string($contents) || trim($contents) === '') {
            return new \stdClass();
        }

        $data = json_decode($contents, false, flags: \JSON_THROW_ON_ERROR);

        if (!$data instanceof \stdClass) {
            throw new \RuntimeException(sprintf('JSON file "%s" must contain an object.', $file));
        }

        return $data;
    }

    /** @param array<string, mixed>|\stdClass $data */
    public function encode(array|\stdClass $data): string
    {
        return json_encode(
            $data,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
