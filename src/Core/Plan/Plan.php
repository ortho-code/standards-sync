<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Plan;

final readonly class Plan
{
    /** @param list<Change> $changes */
    public function __construct(private array $changes)
    {
    }

    /** @return list<Change> */
    public function changes(): array
    {
        return $this->changes;
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
