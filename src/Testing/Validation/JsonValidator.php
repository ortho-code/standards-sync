<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing\Validation;

use JsonException;
use RuntimeException;

/** Every synced .json file must parse — unguarded, since ext-json is always available. Pure format knowledge; tool-level checks are separate validators. */
final readonly class JsonValidator implements SyncedFileValidator
{
    /** JSON5 and JSONC are deliberately not covered: they are different grammars this check would wrongly reject. */
    private const string EXTENSION = '.json';

    public function assertValid(string $path, string $content): void
    {
        if (!str_ends_with($path, self::EXTENSION)) {
            return;
        }

        try {
            json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid JSON: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
