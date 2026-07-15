<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\RuleSet;

use AlleKnalle\StandardsSync\Core\Spec\FileSpec;

interface RuleSetInterface
{
    /** @return list<FileSpec> */
    public function specs(): array;
}
