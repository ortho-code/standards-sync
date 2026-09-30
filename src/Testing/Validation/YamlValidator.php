<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Testing\Validation;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** Parses every synced .yaml and .yml file; its parser, symfony/yaml, installs with the engine, so the format is always validated. */
final readonly class YamlValidator implements SyncedFileValidator
{
    private const array EXTENSIONS = ['.yaml', '.yml'];

    #[\Override]
    public function assertValid(string $path, string $content): void
    {
        if (!array_any(self::EXTENSIONS, static fn(string $extension): bool => str_ends_with($path, $extension))) {
            return;
        }

        try {
            Yaml::parse($content);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid yaml: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
