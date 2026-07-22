<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Text;

/** Indentation characters, named once so format defaults reference them instead of restating the primitive. */
final readonly class Indent
{
    public const string TAB = "\t";

    private const string INDENTED_LINE = '/^([ \t]+)\S/';

    /**
     * The file's own indentation unit, read from its first indented line; null when nothing is indented yet.
     * The fallback is the caller's concern — each format family owns its own default.
     *
     * @param list<string> $lines
     */
    public static function detect(array $lines): ?string
    {
        $indented = array_find($lines, static fn (string $line): bool => preg_match(self::INDENTED_LINE, $line) === 1);
        if ($indented === null) {
            return null;
        }

        preg_match(self::INDENTED_LINE, $indented, $match);

        return $match[1];
    }
}
