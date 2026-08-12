<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Deptrac;

use AlleKnalle\StandardsSync\Core\Rule\FileTarget;

/** The deptrac config file as a rule target: deptrac reads a single default candidate, deptrac.yaml in the working directory — shared so no rule re-derives it. */
final readonly class DeptracConfigFile
{
    public static function target(): FileTarget
    {
        return FileTarget::fromString('deptrac.yaml');
    }
}
