<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Renovate;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;

/**
 * The renovate config file, shared by the family's rules so none re-derives the candidate names.
 * The candidates are renovate's static names; its dynamic per-platform directories (".bitbucket/…") wait for a real repo that carries one.
 */
final readonly class RenovateConfigFile
{
    /** Renovate's static candidate names, in its own precedence order. */
    private const array NAMES = [
        'renovate.json',
        'renovate.jsonc',
        'renovate.json5',
        '.github/renovate.json',
        '.github/renovate.jsonc',
        '.github/renovate.json5',
        '.gitlab/renovate.json',
        '.gitlab/renovate.jsonc',
        '.gitlab/renovate.json5',
        '.renovaterc',
        '.renovaterc.json',
        '.renovaterc.jsonc',
        '.renovaterc.json5',
    ];

    /** The family's one target, the org-chosen creation format's name first — deciding only what a repo without any config gains. */
    public static function target(RenovateConfigFormat $createAs = RenovateConfigFormat::Json): FileTarget
    {
        $created = match ($createAs) {
            RenovateConfigFormat::Json => 'renovate.json',
            RenovateConfigFormat::Json5 => 'renovate.json5',
        };

        return FileTarget::fromStrings($created, ...array_values(array_filter(self::NAMES, static fn (string $name): bool => $name !== $created)));
    }

    public static function isJson5(Path $path): bool
    {
        return str_ends_with($path->value(), '.json5');
    }

    public static function isJsonc(Path $path): bool
    {
        return str_ends_with($path->value(), '.jsonc');
    }
}
