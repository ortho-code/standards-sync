<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing\Validation;

use JsonException;
use RuntimeException;

/** Parses every synced .json5 file; a json5 file synced without colinodell/json5 installed fails loud, so the format is never silently unvalidated. */
final readonly class Json5Validator implements SyncedFileValidator
{
    private const string EXTENSION = '.json5';

    private bool $parserInstalled;

    /** @param bool|null $parserInstalled overrides the colinodell/json5 availability detection; null detects */
    public function __construct(?bool $parserInstalled = null)
    {
        $this->parserInstalled = $parserInstalled ?? function_exists('json5_decode');
    }

    #[\Override]
    public function assertValid(string $path, string $content): void
    {
        if (!str_ends_with($path, self::EXTENSION)) {
            return;
        }

        if (!$this->parserInstalled) {
            throw new RuntimeException(sprintf('The synced %s cannot be validated: install colinodell/json5 (require-dev) to parse synced json5, or leave the Json5Validator out of the validator list.', $path));
        }

        try {
            json5_decode($content);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid JSON5: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
