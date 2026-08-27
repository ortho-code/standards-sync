<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Rule;

use OrthoCode\StandardsSync\Core\Filesystem\Path;

/**
 * Opt-in seam for rules whose target candidates differ in grammar: the fold calls applyAt() instead of apply(), passing the path the target resolved to.
 * The path is data, never filesystem access — the transform stays as pure and idempotent as Rule::apply().
 */
interface AppliesAtPath
{
    /** Pure, idempotent transform like Rule::apply(), additionally told which candidate path resolved. */
    public function applyAt(Path $path, ?string $content): ?string;
}
