<?php

declare(strict_types=1);

namespace StandardsSync\Core\Plan;

final readonly class Plan
{
    /**
     * @param list<Change> $changes
     * @param list<Abstention> $abstentions
     */
    public function __construct(
        private array $changes,
        private array $abstentions = [],
    ) {
    }

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

    /** @return list<Change> */
    public function drift(): array
    {
        return array_values(array_filter($this->changes, static fn (Change $change): bool => $change->isDrift()));
    }

    public function hasDrift(): bool
    {
        return $this->drift() !== [];
    }
}
