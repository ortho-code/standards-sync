<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing\Validation;

use Nette\Neon\Exception;
use Nette\Neon\Neon;
use RuntimeException;

/** Parses every synced .neon file; a .neon file synced without nette/neon installed fails loud, so the format is never silently unvalidated. */
final readonly class NeonValidator implements SyncedFileValidator
{
    private const string EXTENSION = '.neon';

    private bool $parserInstalled;

    /** @param bool|null $parserInstalled overrides the nette/neon availability detection; null detects */
    public function __construct(?bool $parserInstalled = null)
    {
        $this->parserInstalled = $parserInstalled ?? class_exists(Neon::class);
    }

    #[\Override]
    public function assertValid(string $path, string $content): void
    {
        if (!str_ends_with($path, self::EXTENSION)) {
            return;
        }

        if (!$this->parserInstalled) {
            throw new RuntimeException(sprintf('The synced %s cannot be validated: install nette/neon (require-dev) to parse synced neon, or leave the NeonValidator out of the validator list.', $path));
        }

        try {
            Neon::decode($content);
        } catch (Exception $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid neon: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
