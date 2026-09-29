<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Scalar;

/**
 * A scalar written on one line, as neon and yaml both write it: how the written value unquotes, and where its trailing comment starts.
 * Bare and quoted are two spellings of the same value, so matching happens on the unquoted form.
 */
final readonly class InlineScalar
{
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
     * A # opens a comment only at the start or after whitespace — foo#bar is one value.
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
                // Double-quoted strings escape with a backslash; single-quoted strings escape only the quote itself, by doubling it, which the scan reads as close-and-reopen.
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
