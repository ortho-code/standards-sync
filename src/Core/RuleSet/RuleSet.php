<?php

declare(strict_types=1);

namespace StandardsSync\Core\RuleSet;

use StandardsSync\Core\Rule\Rule;

interface RuleSet
{
    /** @return list<Rule> */
    public function rules(): array;
}
