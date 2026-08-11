<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing\Validation;

use Nette\Neon\Neon;
use RuntimeException;

/** Parses every synced .neon file; nette/neon sits in the engine's require-dev only, so the check self-guards and org suites gain it by installing the parser. */
final readonly class NeonValidator implements SyncedFileValidator
{
    private const string EXTENSION = '.neon';

    public function assertValid(string $path, string $content): void
    {
        if (!str_ends_with($path, self::EXTENSION) || !class_exists(Neon::class)) {
            return;
        }

        try {
            Neon::decode($content);
        } catch (\Nette\Neon\Exception $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid neon: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
