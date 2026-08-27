<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\RuleSet;

use OrthoCode\StandardsSync\Core\Rule\Rule;

interface RuleSet
{
    /** @return list<Rule> */
    public function rules(): array;
}
