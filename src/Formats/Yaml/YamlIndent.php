<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml;

/** Yaml's indentation: two spaces by convention — yaml forbids tabs as indentation — used when the file has no indented line to copy. */
final readonly class YamlIndent
{
    public const string DEFAULT = '  ';
}
