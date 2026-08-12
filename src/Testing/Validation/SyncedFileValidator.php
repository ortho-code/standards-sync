<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing\Validation;

/**
 * One format's — or tool's — validity check over synced output.
 * A validator decides applicability from the path and throws when a file it covers is broken — or cannot be checked because its parser is not installed, so a covered format is never silently unvalidated.
 * A suite that ships a format without wanting the check leaves that validator out of the validator list.
 */
interface SyncedFileValidator
{
    /** @throws \RuntimeException when the file is one this validator covers and its content is broken, or its parser is missing */
    public function assertValid(string $path, string $content): void;
}
