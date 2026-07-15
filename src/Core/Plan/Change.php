<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Plan;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;

/** The diff for one file: what is on disk now versus the desired text, and whether that is drift. */
final readonly class Change
{
    public function __construct(
        private Path $path,
        private ChangeKind $kind,
        private ?string $current,
        private string $desired,
    ) {
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function kind(): ChangeKind
    {
        return $this->kind;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function desired(): string
    {
        return $this->desired;
    }

    public function isDrift(): bool
    {
        return $this->kind !== ChangeKind::InSync;
    }
}
