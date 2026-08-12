<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Composer;

use AlleKnalle\StandardsSync\Core\Rule\FileTarget;

/** The composer manifest as a rule target — one candidate, since composer has no dist variant — and the sections composer rules write into. */
final readonly class ComposerManifest
{
    public const string SCRIPTS_SECTION = 'scripts';
    public const string CONFIG_SECTION = 'config';

    private const string FILE = 'composer.json';

    public static function target(): FileTarget
    {
        return FileTarget::fromString(self::FILE);
    }
}
