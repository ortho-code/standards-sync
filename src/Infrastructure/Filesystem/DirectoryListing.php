<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Infrastructure\Filesystem;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** The files under a directory, as sorted paths relative to it; an absent directory lists as empty. */
final readonly class DirectoryListing
{
    /** @param list<string> $relativePaths */
    private function __construct(private array $relativePaths)
    {
    }

    public static function fromDirectory(string $directory): self
    {
        $root = rtrim($directory, '/');
        if (!is_dir($root)) {
            return new self([]);
        }

        $paths = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            $paths[] = substr($file->getPathname(), strlen($root) + 1);
        }
        sort($paths);

        return new self($paths);
    }

    /** @return list<string> */
    public function relativePaths(): array
    {
        return $this->relativePaths;
    }
}
