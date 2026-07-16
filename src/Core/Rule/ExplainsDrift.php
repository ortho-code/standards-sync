<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Rule;

/** Opt-in seam for rules whose drift is not self-evident from the diff. */
interface ExplainsDrift
{
    /** Why the content drifts, e.g. "level 4 is below the minimum of 6". */
    public function explain(?string $content): string;
}
