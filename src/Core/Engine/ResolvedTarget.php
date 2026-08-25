<?php

declare(strict_types=1);

namespace StandardsSync\Core\Engine;

use StandardsSync\Core\Filesystem\Path;

/** A FileTarget resolved against one root: the concrete path and the content currently there (null when the file is absent). */
final readonly class ResolvedTarget
{
    public function __construct(
        private Path $path,
        private ?string $current,
        private ?Path $shadowedBy = null,
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

    /** An existing candidate file the tool reads in preference to this one, when there is one. */
    public function shadowedBy(): ?Path
    {
        return $this->shadowedBy;
    }
}
