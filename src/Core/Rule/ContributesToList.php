<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Rule;

/**
 * Opt-in seam for rules that contribute entries to a list the project shares: every declared entry is the standard's, every other entry is the project's.
 * Contributions to one list in one target merge before the fold, so a standard declared beside another adds to what that one declares rather than folding over it, and the engine records their entries in each root's lock so a later sync can retract the ones no longer declared.
 */
interface ContributesToList
{
    /** The list within the target file, stable across releases; contributions sharing a class, a target and a list key are one list's. */
    public function listKey(): string;

    /** @return non-empty-list<string> the entries contributed to the list, in declared order */
    public function entries(): array;

    /** This contribution combined with a later one to the same list. */
    public function withMerged(self $later): static;

    /**
     * This rule, additionally retracting entries declared at the last sync and declared by nobody now.
     *
     * @param list<string> $retired
     */
    public function withRetired(array $retired): static;
}
