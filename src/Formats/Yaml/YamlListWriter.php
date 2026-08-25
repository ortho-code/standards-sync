<?php

declare(strict_types=1);

namespace StandardsSync\Formats\Yaml;

use StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Ensures one entry in a top-level block-form list section in yaml text.
 * Entries are matched on their unquoted value — two spellings of one path are the same yaml value.
 * Targeted line edits only — everything around the touched lines stays byte-identical; no parse, no reserialization.
 */
final readonly class YamlListWriter
{
    /** An entry line: yaml's block-sequence grammar requires whitespace after the dash, and allows entries at the section's own indentation. */
    private const string ENTRY_LINE = '/^([ \t]*)-[ \t]+(.*)$/';

    private const string COMMENT_LINE = '/^[ \t]*#/';

    private const string ENTRY_PREFIX = '- ';

    /**
     * Ensures the entry in the section: a present entry is kept, an absent one is inserted after the section's last entry.
     * A missing section is created at the top of the document holding just the entry; empty content becomes only that section.
     * An inline-form section is refused: managing an entry needs the block form.
     */
    public static function ensureEntry(string $content, string $section, string $entry): string
    {
        if (trim($content) === '') {
            return self::createSection($section, $entry, YamlIndent::DEFAULT);
        }

        $lines = Lines::split($content);
        $sectionIndex = self::sectionIndex($lines, $section);

        if ($sectionIndex === null) {
            return self::createSection($section, $entry, YamlIndent::fromLines($lines)) . Lines::LINE_BREAK . $content;
        }

        // Scan the section's entries: done when the entry is already there, otherwise remember where the section ends.
        $lastEntryIndex = $sectionIndex;
        $entryIndent = null;
        for ($index = $sectionIndex + 1; $index < count($lines); $index++) {
            if (preg_match(self::ENTRY_LINE, $lines[$index], $match) === 1) {
                // A consumer-annotated entry is still that entry: the trailing comment is not part of the value.
                if (YamlValue::unquote(YamlValue::splitTrailingComment($match[2])[0]) === YamlValue::unquote($entry)) {
                    return $content;
                }
                $entryIndent ??= $match[1];
                $lastEntryIndex = $index;
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

        array_splice($lines, $lastEntryIndex + 1, 0, [($entryIndent ?? YamlIndent::fromLines($lines)) . self::ENTRY_PREFIX . $entry]);

        return Lines::join($lines);
    }

    private static function createSection(string $section, string $entry, string $indent): string
    {
        return $section . ':' . Lines::LINE_BREAK . $indent . self::ENTRY_PREFIX . $entry . Lines::LINE_BREAK;
    }

    /** @param list<string> $lines */
    private static function sectionIndex(array $lines, string $section): ?int
    {
        if (array_any($lines, static fn (string $line): bool => (self::headerValue($line, $section) ?? '') !== '')) {
            throw new RuntimeException(sprintf('The "%s:" section is not a block list; convert it to one "- entry" per line so the entry can be managed.', $section));
        }

        return array_find_key($lines, static fn (string $line): bool => self::headerValue($line, $section) === '');
    }

    /** The value text on the section's header line, trailing comment stripped — or null when the line is not that header. */
    private static function headerValue(string $line, string $section): ?string
    {
        if (!str_starts_with($line, $section . ':')) {
            return null;
        }

        return trim(YamlValue::splitTrailingComment(substr($line, strlen($section) + 1))[0]);
    }
}
