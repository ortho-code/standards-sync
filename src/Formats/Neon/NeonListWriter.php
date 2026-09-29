<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Neon;

use OrthoCode\StandardsSync\Formats\BlockList\BlockListWriter;

/**
 * Reads, ensures or removes entries of a top-level block-form list section in neon text: the block-list writer with neon's indentation default.
 */
final readonly class NeonListWriter
{
    /** @return list<string>|null */
    public static function readList(string $content, string $section): ?array
    {
        return self::writer()->readList($content, $section);
    }

    /** @param list<string> $replacing */
    public static function ensureEntry(string $content, string $section, string $entry, array $replacing = []): string
    {
        return self::writer()->ensureEntry($content, $section, $entry, $replacing);
    }

    /** @param list<string> $entries */
    public static function removeEntries(string $content, string $section, array $entries): string
    {
        return self::writer()->removeEntries($content, $section, $entries);
    }

    private static function writer(): BlockListWriter
    {
        return new BlockListWriter(NeonIndent::DEFAULT);
    }
}
