<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Deptrac;

use OrthoCode\StandardsSync\Core\Rule\FileTarget;

/** The deptrac config file as a rule target: deptrac reads a single default candidate, deptrac.yaml in the working directory — shared so no rule re-derives it. */
final readonly class DeptracConfigFile
{
    private const string CANDIDATE = 'deptrac.yaml';

    public static function target(): FileTarget
    {
        return FileTarget::fromString(self::CANDIDATE);
    }
}
