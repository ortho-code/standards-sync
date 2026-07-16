<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\RuleSet;

use AlleKnalle\StandardsSync\Core\Rule\Rule;

/**
 * Base rule set that collects rules and composes other rule sets.
 * Declaration order is fold order: rules added later fold later, so their edits win over earlier same-target rules.
 */
abstract class ComposableRuleSet implements RuleSet
{
    /** @var list<Rule> */
    private array $rules = [];

    /** @return list<Rule> */
    public function rules(): array
    {
        return $this->rules;
    }

    protected function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
    }

    /** Pulls another rule set's rules in first, so this set's own rules override them. */
    protected function include(RuleSet $ruleSet): void
    {
        foreach ($ruleSet->rules() as $rule) {
            $this->rules[] = $rule;
        }
    }
}
