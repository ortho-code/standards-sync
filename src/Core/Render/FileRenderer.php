<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Render;

use AlleKnalle\StandardsSync\Core\Plan\DesiredFile;

/** Turns a desired file into its full on-disk text; one implementation per file algorithm. */
interface FileRenderer
{
    public function supports(DesiredFile $file): bool;

    public function render(DesiredFile $file, ?string $current): string;
}
