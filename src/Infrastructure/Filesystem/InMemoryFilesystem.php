<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Infrastructure\Filesystem;

use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;

/** Filesystem adapter that keeps files in an array and records every write; backs disk-free syncs (tests, previews). */
final class InMemoryFilesystem implements Filesystem
{
    /** @var array<string, string> */
    private array $files;

    /** @var list<string> */
    private array $written = [];

    /** @param array<string, string> $files */
    public function __construct(array $files = [])
    {
        $this->files = $files;
    }

    public function read(Path $path): ?string
    {
        return $this->files[$path->value()] ?? null;
    }

    public function write(Path $path, string $contents): void
    {
        $this->files[$path->value()] = $contents;
        $this->written[] = $path->value();
    }

    /** @return array<string, string> */
    public function contents(): array
    {
        return $this->files;
    }

    /** @return list<string> */
    public function written(): array
    {
        return $this->written;
    }
}
