<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Scalar\InlineScalar;
use RuntimeException;

/**
 * Neon's scalar value grammar: how a value renders into neon text, and how a written value unquotes and ends at its trailing comment — rules neon shares with yaml.
 */
final readonly class NeonValue
{
    /** Bare strings that need no quoting in neon. */
    private const string SAFE_STRING = '/^[A-Za-z0-9_.\/\\\\-]+$/';

    /** Renders a scalar as neon text: booleans and integers verbatim, strings bare where safe and quoted otherwise. */
    public static function render(bool|int|string $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (preg_match(self::SAFE_STRING, $value) === 1) {
            return $value;
        }
        if (!str_contains($value, '\'')) {
            return '\'' . $value . '\'';
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        throw new RuntimeException(sprintf('The value %s mixes both quote styles and cannot be rendered safely.', $value));
    }

    public static function unquote(string $value): string
    {
        return InlineScalar::unquote($value);
    }

    /** @return array{string, string} */
    public static function splitTrailingComment(string $text): array
    {
        return InlineScalar::splitTrailingComment($text);
    }
}
