<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Php;

use OrthoCode\StandardsSync\Core\Text\Indent;
use OrthoCode\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Edits a fluent builder chain (`return Builder::configure()->…->…;`) in PHP config text.
 * Targeted line edits only — everything around the touched call stays byte-identical; no parse, no reserialization.
 * Brackets are matched with awareness of quoted strings and comments, so neither an entry like `'/a(b)'` nor an apostrophe or bracket in a comment can derail the scan.
 */
final readonly class FluentChainWriter
{
    /** PER coding style indentation, four spaces: the PHP convention, used when the file has no indented line to copy. */
    public const string INDENT = '    ';

    private const string LINE_INDENT = '/^([ \t]*)/';

    private const string LINE_COMMENT = '//';
    private const string BLOCK_COMMENT_OPEN = '/*';
    private const string BLOCK_COMMENT_CLOSE = '*/';
    private const string ATTRIBUTE_OPEN = '#[';

    /**
     * The entries of the block-form array argument of ->method([...]) as written, or null when the chain has no such call.
     *
     * @return list<string>|null
     */
    public static function readArrayEntries(string $content, string $method): ?array
    {
        $array = self::arrayLines($content, $method);
        if ($array === null) {
            return null;
        }

        [$openLine, $closeLine] = $array;

        return array_values(array_filter(self::entries(Lines::split($content), $openLine, $closeLine), static fn(string $text): bool => $text !== ''));
    }

    /**
     * Ensures the block-form array argument of ->method([...]) holds the entry, creating the whole call when absent.
     * An absent entry takes the place of the first entry of $replacing the array holds, keeping that line's indentation, trailing comma and comment, and is otherwise inserted after the last entry.
     * Entries are matched on trimmed text with the trailing comma stripped; a single-line array is refused because managing it would need a rewrite of the caller's formatting.
     *
     * @param list<string> $replacing entries the entry supersedes, matched as the entry is
     */
    public static function ensureArrayEntry(string $content, string $method, string $entry, array $replacing = []): string
    {
        $array = self::arrayLines($content, $method);
        if ($array === null) {
            return self::appendArrayCall($content, $method, $entry);
        }

        [$openLine, $closeLine] = $array;
        $lines = Lines::split($content);
        $entries = self::entries($lines, $openLine, $closeLine);
        if (in_array($entry, $entries, true)) {
            return $content;
        }

        $replacedIndex = array_find_key($entries, static fn(string $text): bool => in_array($text, $replacing, true));
        if ($replacedIndex !== null) {
            $lines[$replacedIndex] = self::withEntry($lines[$replacedIndex], $entries[$replacedIndex], $entry);

            return Lines::join($lines);
        }

        $firstIndex = array_key_first($entries);
        $indent = $firstIndex === null ? self::indentOf($lines[$closeLine]) . self::unit($lines) : self::indentOf($lines[$firstIndex]);
        array_splice($lines, $closeLine, 0, [$indent . $entry . ',']);

        return Lines::join($lines);
    }

    /**
     * Removes every line of the block-form array argument of ->method([...]) holding one of the entries, leaving everything else byte-identical; an absent call or entry leaves the content untouched.
     * The array forms ensureArrayEntry() refuses are refused here too.
     *
     * @param list<string> $entries
     */
    public static function removeArrayEntries(string $content, string $method, array $entries): string
    {
        $array = self::arrayLines($content, $method);
        if ($array === null) {
            return $content;
        }

        [$openLine, $closeLine] = $array;
        $lines = Lines::split($content);
        $removed = array_filter(self::entries($lines, $openLine, $closeLine), static fn(string $text): bool => in_array($text, $entries, true));
        if ($removed === []) {
            return $content;
        }

        return Lines::join(array_values(array_diff_key($lines, $removed)));
    }

    /**
     * A ->method([entry,]) call rendered in block form, for insertion into a created config.
     * A created config has no formatting to respect, so the call carries the canonical unit; the first line starts with `->` unindented, so the creator indents the call into its chain.
     */
    public static function createArrayCall(string $method, string $entry): string
    {
        $lines = self::arrayCallLines($method, $entry, self::INDENT);
        $first = array_shift($lines);

        return Lines::join([$first, ...array_map(static fn(string $line): string => self::INDENT . $line, $lines)]);
    }

    /** Appends ->method([entry,]) as the chain's last call, before the terminating semicolon, following the file's own indentation. */
    private static function appendArrayCall(string $content, string $method, string $entry): string
    {
        $lines = Lines::split($content);
        $lastIndex = self::terminatingLineIndex($lines, $method);

        $indent = self::chainIndent($lines, $lastIndex);
        $call = array_map(static fn(string $line): string => $indent . $line, self::arrayCallLines($method, $entry, self::unit($lines)));
        $call[array_key_last($call)] .= ';';
        $lines[$lastIndex] = substr(rtrim($lines[$lastIndex]), 0, -1);
        array_splice($lines, $lastIndex + 1, 0, $call);

        return Lines::join(array_values($lines));
    }

    /**
     * The canonical block-form shape of a ->method([entry,]) call, unindented: placement decides the indent, this decides the shape.
     *
     * @return non-empty-list<string>
     */
    private static function arrayCallLines(string $method, string $entry, string $unit): array
    {
        return [
            '->' . $method . '([',
            $unit . $entry . ',',
            '])',
        ];
    }

    /**
     * The line holding the chain's terminating semicolon: the last non-blank line, which must end on one.
     *
     * @param list<string> $lines
     */
    private static function terminatingLineIndex(array $lines, string $method): int
    {
        $lastIndex = array_find_key(array_reverse($lines, true), static fn(string $line): bool => trim($line) !== '');
        if ($lastIndex === null || !str_ends_with(rtrim($lines[$lastIndex]), ';')) {
            throw new RuntimeException(sprintf('The config does not end in a terminated statement; ->%s() cannot be appended.', $method));
        }

        return $lastIndex;
    }

    /**
     * The indentation for an appended call: the chain's own when its last call sits on a continuation line, the file's unit otherwise.
     *
     * @param list<string> $lines
     */
    private static function chainIndent(array $lines, int $lastIndex): string
    {
        if (str_starts_with(ltrim($lines[$lastIndex]), '->')) {
            preg_match(self::LINE_INDENT, $lines[$lastIndex], $match);

            return $match[1];
        }

        return self::unit($lines);
    }

    /** @param list<string> $lines */
    private static function unit(array $lines): string
    {
        return Indent::detect($lines) ?? self::INDENT;
    }

    /**
     * The line indices of the array argument's opening and closing brackets, or null when the chain has no ->method(...) call.
     * A non-array argument and a single-line array are refused.
     *
     * @return array{int, int}|null
     */
    private static function arrayLines(string $content, string $method): ?array
    {
        $call = self::findCall($content, $method);
        if ($call === null) {
            return null;
        }

        [$openAt, $closeAt] = $call;
        $argument = trim(substr($content, $openAt + 1, $closeAt - $openAt - 1));
        if (!str_starts_with($argument, '[')) {
            throw new RuntimeException(sprintf('The %s() argument is not an array; the entry cannot be managed.', $method));
        }

        $bracketAt = $openAt + (int) strpos(substr($content, $openAt), '[');
        $closeBracketAt = self::matchingClose($content, $bracketAt, $method);
        if (!str_contains(substr($content, $bracketAt, $closeBracketAt - $bracketAt), Lines::LINE_BREAK)) {
            throw new RuntimeException(sprintf('The %s() array is on a single line; convert it to one entry per line so the entry can be managed.', $method));
        }

        return [self::lineIndexAt($content, $bracketAt), self::lineIndexAt($content, $closeBracketAt)];
    }

    /**
     * Each non-blank line between the array's brackets mapped by index to its entry text; a comment-only line reads as an empty entry.
     *
     * @param list<string> $lines
     * @return array<int, string>
     */
    private static function entries(array $lines, int $openLine, int $closeLine): array
    {
        $entries = [];
        for ($index = $openLine + 1; $index < $closeLine; $index++) {
            if (trim($lines[$index]) !== '') {
                $entries[$index] = self::entryText($lines[$index]);
            }
        }

        return $entries;
    }

    /** The array line holding another entry: its indentation, trailing comma and comment kept, since they belong to the slot rather than to the entry. */
    private static function withEntry(string $line, string $current, string $entry): string
    {
        $indent = self::indentOf($line);

        return $indent . $entry . substr($line, strlen($indent) + strlen($current));
    }

    private static function indentOf(string $line): string
    {
        preg_match(self::LINE_INDENT, $line, $match);

        return $match[1] ?? '';
    }

    /**
     * The extent of the first ->method(...) call: the offsets of its opening parenthesis and the matching close.
     *
     * @return array{int, int}|null
     */
    private static function findCall(string $content, string $method): ?array
    {
        if (preg_match('/->\s*' . preg_quote($method, '/') . '\s*\(/', $content, $match, \PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $openAt = $match[0][1] + strlen($match[0][0]) - 1;

        return [$openAt, self::matchingClose($content, $openAt, $method)];
    }

    /** The offset of the close matching the bracket at $openAt, skipping quoted strings. */
    private static function matchingClose(string $content, int $openAt, string $method): int
    {
        $open = $content[$openAt];
        $close = match ($open) {
            '(' => ')',
            '[' => ']',
            default => throw new RuntimeException(sprintf('The %s() call opens with "%s"; only a parenthesis or a square bracket can be matched.', $method, $open)),
        };

        $depth = 0;
        $quote = null;
        for ($offset = $openAt; $offset < strlen($content); $offset++) {
            $character = $content[$offset];
            if ($quote !== null) {
                if ($character === '\\') {
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
            // A comment is prose: an apostrophe in it would otherwise open a string that never closes, and a bracket in it would count.
            $commentEnd = self::commentEnd($content, $offset, $method);
            if ($commentEnd !== null) {
                $offset = $commentEnd - 1;
                continue;
            }
            if ($character === $open) {
                $depth++;
                continue;
            }
            if ($character === $close && --$depth === 0) {
                return $offset;
            }
        }

        throw new RuntimeException(sprintf('The %s() call never closes its "%s"; the config cannot be edited.', $method, $open));
    }

    /**
     * The offset just past the comment starting at $offset, or null when none starts there; a line comment ends before its line break.
     * `#[` opens an attribute rather than a comment, as it has since PHP 8.0.
     */
    private static function commentEnd(string $content, int $offset, string $method): ?int
    {
        $opening = substr($content, $offset, 2);
        if ($opening === self::BLOCK_COMMENT_OPEN) {
            $close = strpos($content, self::BLOCK_COMMENT_CLOSE, $offset + strlen(self::BLOCK_COMMENT_OPEN));
            if ($close === false) {
                throw new RuntimeException(sprintf('A comment inside the %s() call never closes; the config cannot be edited.', $method));
            }

            return $close + strlen(self::BLOCK_COMMENT_CLOSE);
        }

        if ($opening === self::LINE_COMMENT || ($content[$offset] === '#' && $opening !== self::ATTRIBUTE_OPEN)) {
            $lineBreak = strpos($content, Lines::LINE_BREAK, $offset);

            return $lineBreak === false ? strlen($content) : $lineBreak;
        }

        return null;
    }

    /** The line index the byte offset falls on. */
    private static function lineIndexAt(string $content, int $offset): int
    {
        return substr_count($content, Lines::LINE_BREAK, 0, $offset);
    }

    /**
     * The entry text of an array line: a trailing line comment (`//` or `#`) cut quote-aware — a consumer-annotated entry is still that entry — then whitespace and the trailing comma stripped.
     * Block comments on an entry line stay unhandled: the cost is a one-time visible duplicate, not an edit error.
     */
    private static function entryText(string $line): string
    {
        $quote = null;
        for ($offset = 0; $offset < strlen($line); $offset++) {
            $character = $line[$offset];
            if ($quote !== null) {
                if ($character === '\\') {
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
            if ($character === '#' || ($character === '/' && ($line[$offset + 1] ?? '') === '/')) {
                $line = substr($line, 0, $offset);
                break;
            }
        }

        return rtrim(trim($line), ',');
    }
}
