<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Resolve;

use AlleKnalle\StandardsSync\Core\Spec\FileSpec;

/** Folds all same-label specs for one file into a single block's content, in declaration order. */
interface ContentMerger
{
    /** @param non-empty-list<FileSpec> $specs */
    public function merge(array $specs): string;
}
