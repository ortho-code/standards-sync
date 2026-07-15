<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\RuleSet;

use AlleKnalle\StandardsSync\Core\Spec\FileSpec;

/**
 * Base rule set that collects specs and composes other rule sets.
 * Declaration order is override precedence: specs added later win over earlier same-label specs when merged.
 */
abstract class ComposableRuleSet implements RuleSetInterface
{
    /** @var list<FileSpec> */
    private array $specs = [];

    /** @return list<FileSpec> */
    public function specs(): array
    {
        return $this->specs;
    }

    protected function addSpec(FileSpec $spec): void
    {
        $this->specs[] = $spec;
    }

    /** Pulls another rule set's specs in first, so this set's own specs override them. */
    protected function include(RuleSetInterface $ruleSet): void
    {
        foreach ($ruleSet->specs() as $spec) {
            $this->specs[] = $spec;
        }
    }
}
