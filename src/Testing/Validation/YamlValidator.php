<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing\Validation;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** Parses every synced .yaml and .yml file; a yaml file synced without symfony/yaml installed fails loud, so the format is never silently unvalidated. */
final readonly class YamlValidator implements SyncedFileValidator
{
    private const array EXTENSIONS = ['.yaml', '.yml'];

    private bool $parserInstalled;

    /** @param bool|null $parserInstalled overrides the symfony/yaml availability detection; null detects */
    public function __construct(?bool $parserInstalled = null)
    {
        $this->parserInstalled = $parserInstalled ?? class_exists(Yaml::class);
    }

    public function assertValid(string $path, string $content): void
    {
        if (!array_any(self::EXTENSIONS, static fn (string $extension): bool => str_ends_with($path, $extension))) {
            return;
        }

        if (!$this->parserInstalled) {
            throw new RuntimeException(sprintf('The synced %s cannot be validated: install symfony/yaml (require-dev) to parse synced yaml, or leave the YamlValidator out of the validator list.', $path));
        }

        try {
            Yaml::parse($content);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid yaml: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
