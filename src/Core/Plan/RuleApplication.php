<?php

declare(strict_types=1);

namespace StandardsSync\Core\Plan;

use StandardsSync\Core\Rule\Rule;

/** One rule's step in a file's fold: the content it saw and the content it returned. */
final readonly class RuleApplication
{
    public function __construct(
        private Rule $rule,
        private ?string $before,
        private ?string $after,
    ) {
    }

    public function rule(): Rule
    {
        return $this->rule;
    }

    public function before(): ?string
    {
        return $this->before;
    }

    public function after(): ?string
    {
        return $this->after;
    }

    /** True when this rule changed the running content, i.e. this rule drifted. */
    public function changed(): bool
    {
        return $this->before !== $this->after;
    }
}
