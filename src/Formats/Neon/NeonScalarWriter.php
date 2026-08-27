<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Neon;

use OrthoCode\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Reads or sets one scalar at a nested key path in neon text, creating missing keys along the way when writing.
 * Targeted line edits only — everything around the touched lines stays byte-identical; no parse, no reserialization.
 */
final readonly class NeonScalarWriter
{
    /** The canonical separator between a value and its enforced trailing comment. */
    private const string COMMENT_LEAD = ' # ';

    /**
     * With $comment the enforced line is one unit — value and trailing comment — and a deviation in either rewrites both; without, a project's own trailing comment survives.
     *
     * @param non-empty-list<string> $path
     */
    public static function write(string $content, array $path, bool|int|string $value, ?string $comment = null): string
    {
        $lines = Lines::split($content);
        $unit = NeonIndent::fromLines($lines);

        $rangeStart = 0;
        $rangeEnd = count($lines);
        $indent = '';
        foreach ($path as $position => $key) {
            $prefix = $indent . $key . ':';
            $keyIndex = self::findKey($lines, $prefix, $rangeStart, $rangeEnd);

            if ($keyIndex === null) {
                return self::insertMissing($content, $lines, array_slice($path, $position), $value, $comment, $indent, $unit, $rangeStart, $position === 0);
            }

            $rest = trim(substr($lines[$keyIndex], strlen($prefix)));
            if ($position === count($path) - 1) {
                if ($rest === '' || str_starts_with($rest, '#')) {
                    throw new RuntimeException(sprintf('"%s" holds a section, not a value; it cannot be pinned to a scalar.', implode('.', $path)));
                }

                return self::replaceValue($lines, $keyIndex, $prefix, $value, $comment, $content);
            }

            if ($rest !== '' && !str_starts_with($rest, '#')) {
                throw new RuntimeException(sprintf('"%s" holds a value, not a section; "%s" cannot be pinned beneath it.', implode('.', array_slice($path, 0, $position + 1)), implode('.', $path)));
            }

            [$rangeStart, $rangeEnd, $indent] = self::sectionRange($lines, $keyIndex, $indent, $unit);
        }

        return $content;
    }

    /**
     * Enforces the trailing comment on a written scalar line, leaving the value text verbatim; a line already carrying the canonical comment stays put.
     * When the path is not written or its leaf holds no scalar, the content returns unchanged — annotating is the value enforcement's ride-along, never its replacement.
     *
     * @param non-empty-list<string> $path
     */
    public static function ensureTrailingComment(string $content, array $path, string $comment): string
    {
        $lines = Lines::split($content);
        $unit = NeonIndent::fromLines($lines);

        $rangeStart = 0;
        $rangeEnd = count($lines);
        $indent = '';
        foreach ($path as $position => $key) {
            $prefix = $indent . $key . ':';
            $keyIndex = self::findKey($lines, $prefix, $rangeStart, $rangeEnd);
            if ($keyIndex === null) {
                return $content;
            }

            $rest = trim(substr($lines[$keyIndex], strlen($prefix)));
            if ($position === count($path) - 1) {
                if ($rest === '' || str_starts_with($rest, '#')) {
                    return $content;
                }

                [$written, $current] = NeonValue::splitTrailingComment(substr($lines[$keyIndex], strlen($prefix)));
                if ($current === self::COMMENT_LEAD . $comment) {
                    return $content;
                }

                $lines[$keyIndex] = $prefix . $written . self::COMMENT_LEAD . $comment;

                return Lines::join($lines);
            }

            if ($rest !== '' && !str_starts_with($rest, '#')) {
                return $content;
            }

            [$rangeStart, $rangeEnd, $indent] = self::sectionRange($lines, $keyIndex, $indent, $unit);
        }

        return $content;
    }

    /**
     * The scalar written at a nested key path — verbatim, without any trailing comment — or null when the path is not written or its leaf holds no scalar.
     *
     * @param non-empty-list<string> $path
     */
    public static function read(string $content, array $path): ?string
    {
        $lines = Lines::split($content);
        $unit = NeonIndent::fromLines($lines);

        $rangeStart = 0;
        $rangeEnd = count($lines);
        $indent = '';
        foreach ($path as $position => $key) {
            $prefix = $indent . $key . ':';
            $keyIndex = self::findKey($lines, $prefix, $rangeStart, $rangeEnd);
            if ($keyIndex === null) {
                return null;
            }

            $rest = trim(substr($lines[$keyIndex], strlen($prefix)));
            if ($position === count($path) - 1) {
                $value = trim(NeonValue::splitTrailingComment(substr($lines[$keyIndex], strlen($prefix)))[0]);

                return $value === '' ? null : $value;
            }

            if ($rest !== '' && !str_starts_with($rest, '#')) {
                return null;
            }

            [$rangeStart, $rangeEnd, $indent] = self::sectionRange($lines, $keyIndex, $indent, $unit);
        }

        return null;
    }

    /** @param list<string> $lines */
    private static function findKey(array $lines, string $prefix, int $rangeStart, int $rangeEnd): ?int
    {
        return array_find_key(
            array_slice($lines, $rangeStart, $rangeEnd - $rangeStart, true),
            static fn (string $line): bool => str_starts_with($line, $prefix),
        );
    }

    /**
     * The line range and child indentation of the section opened at $keyIndex; the section ends at the first non-blank line not indented deeper than its parent.
     *
     * @param list<string> $lines
     * @return array{int, int, string}
     */
    private static function sectionRange(array $lines, int $keyIndex, string $parentIndent, string $unit): array
    {
        $childIndent = null;
        $end = count($lines);
        for ($index = $keyIndex + 1; $index < count($lines); $index++) {
            if (trim($lines[$index]) === '') {
                continue;
            }
            preg_match('/^([ \t]*)/', $lines[$index], $match);
            if (strlen($match[1]) <= strlen($parentIndent)) {
                $end = $index;
                break;
            }
            $childIndent ??= $match[1];
        }

        return [$keyIndex + 1, $end, $childIndent ?? $parentIndent . $unit];
    }

    /**
     * @param list<string> $lines
     * @param non-empty-list<string> $remainingPath
     */
    private static function insertMissing(string $content, array $lines, array $remainingPath, bool|int|string $value, ?string $comment, string $indent, string $unit, int $insertAt, bool $topLevel): string
    {
        $suffix = $comment === null ? '' : self::COMMENT_LEAD . $comment;
        $newLines = [];
        foreach ($remainingPath as $depth => $key) {
            $isLeaf = $depth === count($remainingPath) - 1;
            $newLines[] = $indent . str_repeat($unit, $depth) . $key . ':' . ($isLeaf ? ' ' . NeonValue::render($value) . $suffix : '');
        }

        // A missing top-level section goes to the end of the document; a missing nested key becomes its section's first child.
        if ($topLevel) {
            if (trim($content) === '') {
                return Lines::join($newLines) . Lines::LINE_BREAK;
            }

            return rtrim($content, Lines::LINE_BREAK) . Lines::LINE_BREAK . Lines::LINE_BREAK . Lines::join($newLines) . Lines::LINE_BREAK;
        }

        array_splice($lines, $insertAt, 0, $newLines);

        return Lines::join($lines);
    }

    /** @param list<string> $lines */
    private static function replaceValue(array $lines, int $keyIndex, string $prefix, bool|int|string $value, ?string $comment, string $content): string
    {
        [$written, $current] = NeonValue::splitTrailingComment(substr($lines[$keyIndex], strlen($prefix)));
        $rendered = NeonValue::render($value);
        $enforced = $comment === null ? $current : self::COMMENT_LEAD . $comment;
        if (trim($written) === $rendered && $current === $enforced) {
            return $content;
        }

        $lines[$keyIndex] = $prefix . ' ' . $rendered . $enforced;

        return Lines::join($lines);
    }
}
