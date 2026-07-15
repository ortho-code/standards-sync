<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Resolve;

/**
 * Trivial merger where the most-derived spec wins wholesale.
 * The last spec in declaration order replaces any earlier same-label content.
 * A key-aware merger (union .editorconfig sections) is deferred until hierarchy layering needs it.
 */
final class SingleSpecMerger implements ContentMerger
{
    public function merge(array $specs): string
    {
        return $specs[array_key_last($specs)]->content();
    }
}
