<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Rule;

/**
 * What a value-aware rule does when the value it governs is not written in the target at all.
 * Skip: stay out of it — something else (an imported config, a tool default the author accepts) owns the effective value.
 * Write: write the rule's own value, because nothing else supplies one.
 */
enum OnMissing
{
    case Skip;
    case Write;
}
