<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpStan;

use StandardsSync\Core\Rule\FileTarget;

/** The PHPStan config file as a rule target, in PHPStan's own lookup order — shared so no rule re-derives the precedence. */
final readonly class PhpStanConfigFile
{
    private const array CANDIDATES = ['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'];

    public static function target(): FileTarget
    {
        return FileTarget::fromStrings(...self::CANDIDATES);
    }
}
