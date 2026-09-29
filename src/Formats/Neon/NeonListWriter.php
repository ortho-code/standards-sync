<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Neon;

use OrthoCode\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Reads, ensures or removes entries of a top-level block-form list section in neon text.
 * Entries are matched on their unquoted value — two spellings of one path are the same neon value.
 * Targeted line edits only — everything around the touched lines stays byte-identical; no parse, no reserialization.
 */
final readonly class NeonListWriter
{
    private const string ENTRY_LINE = '/^([ \t]+)-[ \t]*(.*)$/';

    private const string COMMENT_LINE = '/^[ \t]*#/';

    private const string ENTRY_PREFIX = '- ';

    /**
     * The section's entries as unquoted values, or null when the document has no such section.
     *
     * @return list<string>|null
     */
    public static function readList(string $content, string $section): ?array
    {
        $lines = Lines::split($content);
        $sectionIndex = self::sectionIndex($lines, $section);

        return $sectionIndex === null ? null : array_values(self::entries($lines, $sectionIndex));
    }

    /**
     * Ensures the entry in the section: a present entry is kept; an absent one takes the place of the first entry of $replacing the section holds, keeping that line's indentation and trailing comment, and is otherwise inserted after the section's last entry.
     * A missing section is created at the top of the document holding just the entry; empty content becomes only that section.
     * An inline-form section is refused: managing an entry needs the block form.
     *
     * @param list<string> $replacing entries the entry supersedes, matched as the entry is
     */
    public static function ensureEntry(string $content, string $section, string $entry, array $replacing = []): string
    {
        if (trim($content) === '') {
            return self::createSection($section, $entry, NeonIndent::DEFAULT);
        }

        $lines = Lines::split($content);
        $sectionIndex = self::sectionIndex($lines, $section);

        if ($sectionIndex === null) {
            return self::createSection($section, $entry, NeonIndent::fromLines($lines)) . Lines::LINE_BREAK . $content;
        }

        $entries = self::entries($lines, $sectionIndex);
        if (in_array(NeonValue::unquote($entry), $entries, true)) {
            return $content;
        }

        $superseded = array_map(NeonValue::unquote(...), $replacing);
        $replacedIndex = array_find_key($entries, static fn(string $value): bool => in_array($value, $superseded, true));
        if ($replacedIndex !== null) {
            $lines[$replacedIndex] = self::withValue($lines[$replacedIndex], $entry);

            return Lines::join($lines);
        }

        $firstIndex = array_key_first($entries);
        $indent = $firstIndex === null ? NeonIndent::fromLines($lines) : self::indentOf($lines[$firstIndex]);
        array_splice($lines, (array_key_last($entries) ?? $sectionIndex) + 1, 0, [$indent . self::ENTRY_PREFIX . $entry]);

        return Lines::join($lines);
    }

    /**
     * Removes every line of the section holding one of the entries, leaving everything else byte-identical; an absent section or entry leaves the content untouched.
     * An inline-form section is refused, as ensureEntry() refuses it.
     *
     * @param list<string> $entries
     */
    public static function removeEntries(string $content, string $section, array $entries): string
    {
        $lines = Lines::split($content);
        $sectionIndex = self::sectionIndex($lines, $section);
        if ($sectionIndex === null) {
            return $content;
        }

        $unwanted = array_map(NeonValue::unquote(...), $entries);
        $removed = array_filter(self::entries($lines, $sectionIndex), static fn(string $value): bool => in_array($value, $unwanted, true));
        if ($removed === []) {
            return $content;
        }

        return Lines::join(array_values(array_diff_key($lines, $removed)));
    }

    private static function createSection(string $section, string $entry, string $indent): string
    {
        return $section . ':' . Lines::LINE_BREAK . $indent . self::ENTRY_PREFIX . $entry . Lines::LINE_BREAK;
    }

    /**
     * The section's entry lines, from its header to the first line that is neither an entry, a comment nor blank: each line's index mapped to its unquoted value.
     *
     * @param list<string> $lines
     * @return array<int, string>
     */
    private static function entries(array $lines, int $sectionIndex): array
    {
        $entries = [];
        $counter = count($lines);
        for ($index = $sectionIndex + 1; $index < $counter; $index++) {
            if (preg_match(self::ENTRY_LINE, $lines[$index], $match) === 1) {
                // A consumer-annotated entry is still that entry: the trailing comment is not part of the value.
                $entries[$index] = NeonValue::unquote(NeonValue::splitTrailingComment($match[2])[0]);
                continue;
            }
            // A comment line neither ends the section nor holds an entry — the entries around it still count.
            if (preg_match(self::COMMENT_LINE, $lines[$index]) === 1) {
                continue;
            }
            if (trim($lines[$index]) !== '') {
                break;
            }
        }

        return $entries;
    }

    private static function indentOf(string $entryLine): string
    {
        preg_match(self::ENTRY_LINE, $entryLine, $match);

        return $match[1] ?? '';
    }

    /** The entry line holding another value: its indentation, dash spacing and trailing comment kept, since they belong to the slot rather than to the value. */
    private static function withValue(string $entryLine, string $entry): string
    {
        preg_match(self::ENTRY_LINE, $entryLine, $match);
        $tail = $match[2] ?? '';
        [, $comment] = NeonValue::splitTrailingComment($tail);

        return substr($entryLine, 0, strlen($entryLine) - strlen($tail)) . $entry . $comment;
    }

    /** @param list<string> $lines */
    private static function sectionIndex(array $lines, string $section): ?int
    {
        if (array_any($lines, static fn(string $line): bool => (self::headerValue($line, $section) ?? '') !== '')) {
            throw new RuntimeException(sprintf('The "%s:" section is not a block list; convert it to one "- entry" per line so the entry can be managed.', $section));
        }

        return array_find_key($lines, static fn(string $line): bool => self::headerValue($line, $section) === '');
    }

    /** The value text on the section's header line, trailing comment stripped — or null when the line is not that header. */
    private static function headerValue(string $line, string $section): ?string
    {
        if (!str_starts_with($line, $section . ':')) {
            return null;
        }

        return trim(NeonValue::splitTrailingComment(substr($line, strlen($section) + 1))[0]);
    }
}
