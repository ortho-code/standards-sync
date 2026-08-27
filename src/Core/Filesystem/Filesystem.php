<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Filesystem;

/** The one I/O boundary: read and write file contents by path. */
interface Filesystem
{
    public function read(Path $path): ?string;

    public function write(Path $path, string $contents): void;
}
