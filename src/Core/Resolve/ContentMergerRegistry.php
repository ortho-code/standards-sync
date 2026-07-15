<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Resolve;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;

/** Chooses the merger for a file, by relative path, falling back to a default. */
final readonly class ContentMergerRegistry
{
    /** @param array<string, ContentMerger> $byPath */
    public function __construct(
        private ContentMerger $default,
        private array $byPath = [],
    ) {
    }

    public function mergerFor(Path $relativePath): ContentMerger
    {
        return $this->byPath[$relativePath->value()] ?? $this->default;
    }

    public static function default(): self
    {
        return new self(new SingleSpecMerger(), ['.gitignore' => new LineUnionMerger()]);
    }
}
