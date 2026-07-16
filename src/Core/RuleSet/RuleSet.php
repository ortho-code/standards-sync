<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\RuleSet;

use AlleKnalle\StandardsSync\Core\Rule\Rule;

interface RuleSet
{
    /** @return list<Rule> */
    public function rules(): array;
}
