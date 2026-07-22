<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing;

use AlleKnalle\StandardsSync\Core\Text\Lines;

/** Builds a test fixture as the file looks on disk: the nowdoc body plus the trailing line break every file ends with. */
final readonly class FileContent
{
    public static function fromString(string $content): string
    {
        return $content . Lines::LINE_BREAK;
    }
}
