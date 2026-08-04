<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Formats\Neon;

use AlleKnalle\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Ensures one entry in a top-level block-form list section in neon text.
 * Entries are matched on their unquoted value — two spellings of one path are the same neon value.
 * Targeted line edits only — everything around the touched lines stays byte-identical; no parse, no reserialization.
 */
final readonly class NeonListWriter
{
    /**
     * Ensures the entry in the section: a present entry is kept, an absent one is inserted after the section's last entry.
     * A missing section is created at the top of the document holding just the entry; empty content becomes only that section.
     * An inline-form section is refused: managing an entry needs the block form.
     */
    public static function ensureEntry(string $content, string $section, string $entry): string
    {
        if (trim($content) === '') {
            return self::createSection($section, $entry, NeonIndent::DEFAULT);
        }

        $lines = Lines::split($content);
        $sectionIndex = self::sectionIndex($lines, $section);

        if ($sectionIndex === null) {
            return self::createSection($section, $entry, NeonIndent::fromLines($lines)) . Lines::LINE_BREAK . $content;
        }

        // Scan the section's entries: done when the entry is already there, otherwise remember where the section ends.
        $lastEntryIndex = $sectionIndex;
        $entryIndent = null;
        for ($index = $sectionIndex + 1; $index < count($lines); $index++) {
            if (preg_match('/^([ \t]+)-[ \t]*(.*)$/', $lines[$index], $match) === 1) {
                // A consumer-annotated entry is still that entry: the trailing comment is not part of the value.
                if (NeonValue::unquote(NeonValue::splitTrailingComment($match[2])[0]) === NeonValue::unquote($entry)) {
                    return $content;
                }
                $entryIndent ??= $match[1];
                $lastEntryIndex = $index;
                continue;
            }
            if (trim($lines[$index]) !== '') {
                break;
            }
        }

        array_splice($lines, $lastEntryIndex + 1, 0, [($entryIndent ?? NeonIndent::fromLines($lines)) . '- ' . $entry]);

        return Lines::join($lines);
    }

    private static function createSection(string $section, string $entry, string $indent): string
    {
        return $section . ':' . Lines::LINE_BREAK . $indent . '- ' . $entry . Lines::LINE_BREAK;
    }

    /** @param list<string> $lines */
    private static function sectionIndex(array $lines, string $section): ?int
    {
        $inlineForm = '/^' . preg_quote($section . ':', '/') . '[ \t]*\S/';
        if (array_any($lines, static fn (string $line): bool => preg_match($inlineForm, $line) === 1)) {
            throw new RuntimeException(sprintf('The "%s:" section is not a block list; convert it to one "- entry" per line so the entry can be managed.', $section));
        }

        return array_find_key($lines, static fn (string $line): bool => rtrim($line) === $section . ':');
    }
}
