<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Plan;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;

/**
 * The intended state of one file: the managed blocks it should contain.
 * The file is the planning unit, so several packages or hierarchy layers co-manage it as separate blocks.
 */
final readonly class DesiredFile
{
    /** @param list<ManagedBlock> $blocks */
    public function __construct(
        private Path $path,
        private array $blocks,
    ) {
    }

    public function path(): Path
    {
        return $this->path;
    }

    /** @return list<ManagedBlock> */
    public function blocks(): array
    {
        return $this->blocks;
    }
}
