<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Plan;

use OrthoCode\StandardsSync\Core\Filesystem\Path;

/**
 * A list the lock recorded that no rule contributes to any more.
 * Nothing retracts it, so whatever is left of it in the file is the project's own.
 */
final readonly class ForgottenList
{
    public function __construct(
        private Path $file,
        private string $listKey,
    ) {}

    public function file(): Path
    {
        return $this->file;
    }

    public function listKey(): string
    {
        return $this->listKey;
    }
}
