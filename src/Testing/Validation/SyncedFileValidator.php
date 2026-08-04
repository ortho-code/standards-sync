<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing\Validation;

/**
 * One format's — or tool's — validity check over synced output.
 * A validator decides applicability from the path and throws when a file it covers is broken; an unavailable parser makes it a no-op, so org suites opt in by installing the parser.
 */
interface SyncedFileValidator
{
    /** @throws \RuntimeException when the file is one this validator covers and its content is broken */
    public function assertValid(string $path, string $content): void;
}
