<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Formats\Yaml;

/**
 * Yaml's scalar value grammar: how a written value unquotes, and where a trailing comment starts.
 * Bare and quoted are two spellings of the same yaml value, so matching happens on the unquoted form.
 */
final readonly class YamlValue
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
     * A # opens a comment only at the start or after whitespace — in yaml, foo#bar is one scalar.
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
                // Yaml's double-quoted strings escape with a backslash; single-quoted strings escape only the quote itself, by doubling it, which the scan reads as close-and-reopen.
                if ($quote === '"' && $character === '\\') {
                    $offset++;
                    continue;
                }
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($character === "'" || $character === '"') {
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
