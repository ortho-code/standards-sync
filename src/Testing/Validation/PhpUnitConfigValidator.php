<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing\Validation;

use Composer\InstalledVersions;
use DOMDocument;
use RuntimeException;

/**
 * Validates a synced phpunit config against phpunit's shipped phpunit.xsd; a phpunit config synced without phpunit/phpunit installed fails loud, so the check is never silently skipped.
 * Tool knowledge, deliberately beside the format validators: the candidate names are phpunit's own discovery list, restated because the Testing layer may not depend on the rule library.
 * The config is namespace-free and validates as-is — no xmlns handling, unlike the psalm check.
 */
final readonly class PhpUnitConfigValidator implements SyncedFileValidator
{
    private const array CANDIDATES = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];

    private ?string $schema;

    /** @param bool|null $phpunitInstalled overrides the phpunit/phpunit availability detection; null locates its shipped schema through composer's install record */
    public function __construct(?bool $phpunitInstalled = null)
    {
        $this->schema = $phpunitInstalled === false ? null : self::installedSchema();
    }

    #[\Override]
    public function assertValid(string $path, string $content): void
    {
        if (!in_array(basename($path), self::CANDIDATES, true)) {
            return;
        }

        if ($this->schema === null) {
            throw new RuntimeException(sprintf('The synced %s cannot be validated against the phpunit config schema: install phpunit/phpunit (require-dev), or leave the PhpUnitConfigValidator out of the validator list.', $path));
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($content)) {
                throw new RuntimeException(sprintf('The synced %s is not well-formed XML: %s', $path, self::firstLibxmlError()));
            }

            if (!$document->schemaValidate($this->schema)) {
                throw new RuntimeException(sprintf('The synced %s violates the phpunit config schema: %s', $path, self::firstLibxmlError()));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** PHPUnit's shipped schema, located through composer's install record — null when phpunit is not installed in the running suite. */
    private static function installedSchema(): ?string
    {
        if (!InstalledVersions::isInstalled('phpunit/phpunit')) {
            return null;
        }

        $installPath = InstalledVersions::getInstallPath('phpunit/phpunit');
        if ($installPath === null) {
            return null;
        }

        $schema = $installPath . '/phpunit.xsd';

        return is_file($schema) ? $schema : null;
    }

    private static function firstLibxmlError(): string
    {
        $error = libxml_get_errors()[0] ?? null;

        return $error instanceof \LibXMLError ? trim($error->message) : 'unknown error';
    }
}
