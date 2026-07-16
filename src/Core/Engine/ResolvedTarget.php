<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Engine;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;

/** A FileTarget resolved against one root: the concrete path and the content currently there (null when the file is absent). */
final readonly class ResolvedTarget
{
    public function __construct(
        private Path $path,
        private ?string $current,
    ) {
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function current(): ?string
    {
        return $this->current;
    }
}
