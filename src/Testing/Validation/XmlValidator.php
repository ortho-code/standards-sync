<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing\Validation;

use DOMDocument;
use RuntimeException;

/** Every synced .xml file must be well-formed — unguarded, since ext-dom is a hard engine requirement. Pure format knowledge; tool-level schema checks are separate validators. */
final readonly class XmlValidator implements SyncedFileValidator
{
    private const string EXTENSION = '.xml';

    public function assertValid(string $path, string $content): void
    {
        if (!str_ends_with($path, self::EXTENSION)) {
            return;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            if (!new DOMDocument()->loadXML($content)) {
                throw new RuntimeException(sprintf('The synced %s is not well-formed XML: %s', $path, self::firstLibxmlError()));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function firstLibxmlError(): string
    {
        $error = libxml_get_errors()[0] ?? null;

        return $error === null ? 'unknown error' : trim($error->message);
    }
}
