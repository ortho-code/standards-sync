<?php

declare(strict_types=1);

namespace StandardsSync\Infrastructure\Filesystem;

use StandardsSync\Core\Filesystem\Filesystem;
use StandardsSync\Core\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystemComponent;

/** Filesystem adapter backed by symfony/filesystem; the pipeline's one reader and writer of disk. */
final readonly class SymfonyFilesystem implements Filesystem
{
    private SymfonyFilesystemComponent $filesystem;

    public function __construct()
    {
        $this->filesystem = new SymfonyFilesystemComponent();
    }

    public function read(Path $path): ?string
    {
        $location = $path->value();

        /*
         * A file that does not exist yet is not drift, so the port returns null for it.
         * Anything that does exist is handed to readFile(), which surfaces a directory or an unreadable file as an exception rather than a silent null.
         */
        if (!$this->filesystem->exists($location)) {
            return null;
        }

        return $this->filesystem->readFile($location);
    }

    public function write(Path $path, string $contents): void
    {
        $this->filesystem->dumpFile($path->value(), $contents);
    }
}
