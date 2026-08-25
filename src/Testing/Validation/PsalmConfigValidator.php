<?php

declare(strict_types=1);

namespace StandardsSync\Testing\Validation;

use Composer\InstalledVersions;
use DOMDocument;
use RuntimeException;

/**
 * Validates a synced psalm config against psalm's shipped config.xsd; a psalm config synced without vimeo/psalm installed fails loud, so the check is never silently skipped.
 * Tool knowledge, deliberately beside the format validators: the candidate names are psalm's own discovery list, restated because the Testing layer may not depend on the rule library.
 * Replicates psalm's missing-xmlns tolerance (injected in memory before validating), so the check accepts exactly what psalm accepts.
 */
final readonly class PsalmConfigValidator implements SyncedFileValidator
{
    private const array CANDIDATES = ['psalm.xml', 'psalm.xml.dist', 'psalm.dist.xml'];
    private const string XMLNS = 'https://getpsalm.org/schema/config';

    private ?string $schema;

    /** @param bool|null $psalmInstalled overrides the vimeo/psalm availability detection; null locates its shipped schema through composer's install record */
    public function __construct(?bool $psalmInstalled = null)
    {
        $this->schema = $psalmInstalled === false ? null : self::installedSchema();
    }

    public function assertValid(string $path, string $content): void
    {
        if (!in_array(basename($path), self::CANDIDATES, true)) {
            return;
        }

        if ($this->schema === null) {
            throw new RuntimeException(sprintf('The synced %s cannot be validated against the psalm config schema: install vimeo/psalm (require-dev), or leave the PsalmConfigValidator out of the validator list.', $path));
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($content)) {
                throw new RuntimeException(sprintf('The synced %s is not well-formed XML: %s', $path, self::firstLibxmlError()));
            }

            if ($document->documentElement?->namespaceURI === null) {
                $document = new DOMDocument();
                $document->loadXML((string) preg_replace('/<psalm(?=[\s\/>])/', sprintf('<psalm xmlns="%s"', self::XMLNS), $content, 1));
            }

            if (!$document->schemaValidate($this->schema)) {
                throw new RuntimeException(sprintf('The synced %s violates the psalm config schema: %s', $path, self::firstLibxmlError()));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Psalm's shipped schema, located through composer's install record — null when psalm is not installed in the running suite. */
    private static function installedSchema(): ?string
    {
        if (!InstalledVersions::isInstalled('vimeo/psalm')) {
            return null;
        }

        $installPath = InstalledVersions::getInstallPath('vimeo/psalm');
        if ($installPath === null) {
            return null;
        }

        $schema = $installPath . '/config.xsd';

        return is_file($schema) ? $schema : null;
    }

    private static function firstLibxmlError(): string
    {
        $error = libxml_get_errors()[0] ?? null;

        return $error === null ? 'unknown error' : trim($error->message);
    }
}
