<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Plan;

final readonly class Plan
{
    /**
     * @param list<Change> $changes
     * @param list<Abstention> $abstentions
     * @param list<ForgottenList> $forgottenLists
     */
    public function __construct(
        private array $changes,
        private array $abstentions = [],
        private array $forgottenLists = [],
    ) {}

    /** @return list<Change> */
    public function changes(): array
    {
        return $this->changes;
    }

    /**
     * The files no rule had an opinion about; they are reported, never written, and never drift.
     *
     * @return list<Abstention>
     */
    public function abstentions(): array
    {
        return $this->abstentions;
    }

    /**
     * The lists the lock recorded that no rule contributes to any more; they are reported, and whatever is left of them stays.
     *
     * @return list<ForgottenList>
     */
    public function forgottenLists(): array
    {
        return $this->forgottenLists;
    }

    /** @return list<Change> */
    public function drift(): array
    {
        return array_values(array_filter($this->changes, static fn(Change $change): bool => $change->isDrift()));
    }

    public function hasDrift(): bool
    {
        return $this->drift() !== [];
    }
}
