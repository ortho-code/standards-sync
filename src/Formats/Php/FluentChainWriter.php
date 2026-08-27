<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Php;

use OrthoCode\StandardsSync\Core\Text\Indent;
use OrthoCode\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Edits a fluent builder chain (`return Builder::configure()->…->…;`) in PHP config text.
 * Targeted line edits only — everything around the touched call stays byte-identical; no parse, no reserialization.
 * Brackets are matched with awareness of quoted strings, so an entry like `'/a(b)'` cannot derail the scan.
 */
final readonly class FluentChainWriter
{
    /** PER coding style indentation, four spaces: the PHP convention, used when the file has no indented line to copy. */
    public const string INDENT = '    ';

    private const string LINE_INDENT = '/^([ \t]*)/';

    /**
     * Ensures the block-form array argument of ->method([...]) holds the entry, creating the whole call when absent.
     * Entries are matched on trimmed text with the trailing comma stripped; a single-line array is refused because managing it would need a rewrite of the caller's formatting.
     */
    public static function ensureArrayEntry(string $content, string $method, string $entry): string
    {
        $call = self::findCall($content, $method);
        if ($call === null) {
            return self::appendArrayCall($content, $method, $entry);
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

        $lines = Lines::split($content);
        $openLine = self::lineIndexAt($content, $bracketAt);
        $closeLine = self::lineIndexAt($content, $closeBracketAt);

        $entryIndent = null;
        for ($index = $openLine + 1; $index < $closeLine; $index++) {
            if (trim($lines[$index]) === '') {
                continue;
            }
            if (self::entryText($lines[$index]) === $entry) {
                return $content;
            }
            preg_match(self::LINE_INDENT, $lines[$index], $match);
            $entryIndent ??= $match[1];
        }

        preg_match(self::LINE_INDENT, $lines[$closeLine], $match);
        array_splice($lines, $closeLine, 0, [($entryIndent ?? $match[1] . self::unit($lines)) . $entry . ',']);

        return Lines::join($lines);
    }

    /**
     * A ->method([entry,]) call rendered in block form, for insertion into a created config.
     * A created config has no formatting to respect, so the call carries the canonical unit; the first line starts with `->` unindented, so the creator indents the call into its chain.
     */
    public static function createArrayCall(string $method, string $entry): string
    {
        $lines = self::arrayCallLines($method, $entry, self::INDENT);
        $first = array_shift($lines);

        return Lines::join([$first, ...array_map(static fn (string $line): string => self::INDENT . $line, $lines)]);
    }

    /** Appends ->method([entry,]) as the chain's last call, before the terminating semicolon, following the file's own indentation. */
    private static function appendArrayCall(string $content, string $method, string $entry): string
    {
        $lines = Lines::split($content);
        $lastIndex = self::terminatingLineIndex($lines, $method);

        $indent = self::chainIndent($lines, $lastIndex);
        $call = array_map(static fn (string $line): string => $indent . $line, self::arrayCallLines($method, $entry, self::unit($lines)));
        $call[array_key_last($call)] .= ';';
        $lines[$lastIndex] = substr(rtrim($lines[$lastIndex]), 0, -1);
        array_splice($lines, $lastIndex + 1, 0, $call);

        return Lines::join($lines);
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
        $lastIndex = array_find_key(array_reverse($lines, true), static fn (string $line): bool => trim($line) !== '');
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
            if ($character === "'" || $character === '"') {
                $quote = $character;
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
            if ($character === "'" || $character === '"') {
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
