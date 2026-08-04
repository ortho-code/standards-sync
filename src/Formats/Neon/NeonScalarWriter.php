<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Formats\Neon;

use AlleKnalle\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Reads or sets one scalar at a nested key path in neon text, creating missing keys along the way when writing.
 * Targeted line edits only — everything around the touched lines stays byte-identical; no parse, no reserialization.
 */
final readonly class NeonScalarWriter
{
    /** @param non-empty-list<string> $path */
    public static function write(string $content, array $path, bool|int|string $value): string
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
                return self::insertMissing($content, $lines, array_slice($path, $position), $value, $indent, $unit, $rangeStart, $position === 0);
            }

            $rest = trim(substr($lines[$keyIndex], strlen($prefix)));
            if ($position === count($path) - 1) {
                if ($rest === '' || str_starts_with($rest, '#')) {
                    throw new RuntimeException(sprintf('"%s" holds a section, not a value; it cannot be pinned to a scalar.', implode('.', $path)));
                }

                return self::replaceValue($lines, $keyIndex, $prefix, $value, $content);
            }

            if ($rest !== '' && !str_starts_with($rest, '#')) {
                throw new RuntimeException(sprintf('"%s" holds a value, not a section; "%s" cannot be pinned beneath it.', implode('.', array_slice($path, 0, $position + 1)), implode('.', $path)));
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
    private static function insertMissing(string $content, array $lines, array $remainingPath, bool|int|string $value, string $indent, string $unit, int $insertAt, bool $topLevel): string
    {
        $newLines = [];
        foreach ($remainingPath as $depth => $key) {
            $isLeaf = $depth === count($remainingPath) - 1;
            $newLines[] = $indent . str_repeat($unit, $depth) . $key . ':' . ($isLeaf ? ' ' . NeonValue::render($value) : '');
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
    private static function replaceValue(array $lines, int $keyIndex, string $prefix, bool|int|string $value, string $content): string
    {
        [$written, $comment] = NeonValue::splitTrailingComment(substr($lines[$keyIndex], strlen($prefix)));
        $rendered = NeonValue::render($value);
        if (trim($written) === $rendered) {
            return $content;
        }

        $lines[$keyIndex] = $prefix . ' ' . $rendered . $comment;

        return Lines::join($lines);
    }
}
