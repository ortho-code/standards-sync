<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Rule;

/**
 * Opt-in seam for rules that contribute entries to a list.
 * Contributions to one list in one target merge before the fold, so a standard declared beside another adds to what that one declares rather than folding over it.
 */
interface ContributesToList
{
    /** The list within the target file, stable across releases; contributions sharing a class, a target and a list key are one list's. */
    public function listKey(): string;

    /** This contribution combined with a later one to the same list. */
    public function withMerged(self $later): static;
}
