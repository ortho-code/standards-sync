<?php

declare(strict_types=1);

namespace StandardsSync\Formats\Yaml;

use StandardsSync\Core\Text\Indent;

/** Yaml's indentation: two spaces by convention — yaml forbids tabs as indentation — used when the file has no indented line to copy. */
final readonly class YamlIndent
{
    public const string DEFAULT = '  ';

    /**
     * The indentation unit the file uses, or the yaml default when it shows none.
     *
     * @param list<string> $lines
     */
    public static function fromLines(array $lines): string
    {
        return Indent::detect($lines) ?? self::DEFAULT;
    }
}
