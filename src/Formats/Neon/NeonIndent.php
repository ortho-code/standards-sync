<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Neon;

use OrthoCode\StandardsSync\Core\Text\Indent;

/** Neon's indentation: the documented default is a tab, used when the file has no indented line to copy. */
final readonly class NeonIndent
{
    public const string DEFAULT = Indent::TAB;

    /**
     * The indentation unit the file uses, or the neon default when it shows none.
     *
     * @param list<string> $lines
     */
    public static function fromLines(array $lines): string
    {
        return Indent::detect($lines) ?? self::DEFAULT;
    }
}
