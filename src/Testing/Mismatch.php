<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing;

use OrthoCode\StandardsSync\Core\Filesystem\Path;

/** One file whose synced content did not match the fixture's expected tree (a null side means the file was only present on the other side). */
final readonly class Mismatch
{
    public function __construct(
        private Path $path,
        private ?string $expected,
        private ?string $actual,
    ) {}

    public function path(): Path
    {
        return $this->path;
    }

    public function expected(): ?string
    {
        return $this->expected;
    }

    public function actual(): ?string
    {
        return $this->actual;
    }
}
