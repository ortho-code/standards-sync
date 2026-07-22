<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Text;

/**
 * The engine's canonical line convention: content is LF-separated.
 * Splitting and joining go through here, so any future CRLF handling has a single seam.
 */
final readonly class Lines
{
    public const string LINE_BREAK = "\n";

    /** @return list<string> */
    public static function split(string $content): array
    {
        return explode(self::LINE_BREAK, $content);
    }

    /** @param list<string> $lines */
    public static function join(array $lines): string
    {
        return implode(self::LINE_BREAK, $lines);
    }
}
