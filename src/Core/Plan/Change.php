<?php

declare(strict_types=1);

namespace StandardsSync\Core\Plan;

use StandardsSync\Core\Filesystem\Path;

/** The diff for one file: what is on disk now versus the desired text, and which rules produced it. */
final readonly class Change
{
    /** @param list<RuleApplication> $applications */
    public function __construct(
        private Path $path,
        private ChangeKind $kind,
        private ?string $current,
        private string $desired,
        private array $applications,
        private ?Path $shadowedBy = null,
    ) {
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function kind(): ChangeKind
    {
        return $this->kind;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function desired(): string
    {
        return $this->desired;
    }

    /** @return list<RuleApplication> */
    public function applications(): array
    {
        return $this->applications;
    }

    /** An existing candidate file the tool reads in preference to this one, when there is one. */
    public function shadowedBy(): ?Path
    {
        return $this->shadowedBy;
    }

    /**
     * The rules that changed the running content in this file's fold.
     *
     * @return list<RuleApplication>
     */
    public function driftingApplications(): array
    {
        return array_values(array_filter(
            $this->applications,
            static fn (RuleApplication $application): bool => $application->changed(),
        ));
    }

    public function isDrift(): bool
    {
        return $this->kind !== ChangeKind::InSync;
    }
}
