<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Testing\Validation;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** Parses every synced .yaml and .yml file; symfony/yaml sits in the engine's require-dev only, so the check self-guards and org suites gain it by installing the parser. */
final readonly class YamlValidator implements SyncedFileValidator
{
    private const array EXTENSIONS = ['.yaml', '.yml'];

    public function assertValid(string $path, string $content): void
    {
        if (!array_any(self::EXTENSIONS, static fn (string $extension): bool => str_ends_with($path, $extension)) || !class_exists(Yaml::class)) {
            return;
        }

        try {
            Yaml::parse($content);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('The synced %s is not valid yaml: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }
}
