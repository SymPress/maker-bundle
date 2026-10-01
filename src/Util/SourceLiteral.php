<?php

declare(strict_types=1);

namespace SymPress\MakerBundle\Util;

final class SourceLiteral
{
    public static function javascript(string $value): string
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
        return "'" . str_replace(["'", '\\"'], ["\\'", '"'], substr($encoded, 1, -1)) . "'";
    }

    public static function identifier(string $value): void
    {
        if (preg_match('/^[a-zA-Z_\\x80-\\xff][a-zA-Z0-9_\\x80-\\xff]*$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Generated PHP identifiers must be valid identifier names.');
        }
    }
}
