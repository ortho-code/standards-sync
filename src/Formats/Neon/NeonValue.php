<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Neon;

use RuntimeException;

/**
 * Neon's scalar value grammar: how a value renders into neon text, and how a written value unquotes.
 * Bare and quoted are two spellings of the same neon value, so matching happens on the unquoted form.
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

    /** The unquoted value: whitespace trimmed, one pair of surrounding quotes stripped. */
    public static function unquote(string $value): string
    {
        $bare = trim($value);
        if (preg_match('/^([\'"])(.*)\1$/', $bare, $match) === 1) {
            return $match[2];
        }

        return $bare;
    }

    /**
     * Splits a written value from its trailing comment, quote-aware: a # inside a quoted value is content, not a comment boundary.
     * A # opens a comment only at the start or after whitespace — in neon, foo#bar is one value.
     * The comment part carries its leading whitespace and is empty when there is no comment.
     *
     * @return array{string, string}
     */
    public static function splitTrailingComment(string $text): array
    {
        $quote = null;
        for ($offset = 0; $offset < strlen($text); $offset++) {
            $character = $text[$offset];
            if ($quote !== null) {
                // Neon's double-quoted strings escape with a backslash; single-quoted strings have no escapes.
                if ($quote === '"' && $character === '\\') {
                    $offset++;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($character === '\'' || $character === '"') {
                $quote = $character;
                continue;
            }
            if ($character === '#' && ($offset === 0 || $text[$offset - 1] === ' ' || $text[$offset - 1] === "\t")) {
                $value = rtrim(substr($text, 0, $offset));

                return [$value, substr($text, strlen($value))];
            }
        }

        return [$text, ''];
    }
}
