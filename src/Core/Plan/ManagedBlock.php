<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Plan;

use AlleKnalle\StandardsSync\Core\Block\Label;

/** A resolved block ready to render: one label's merged content. */
final readonly class ManagedBlock
{
    public function __construct(
        private Label $label,
        private string $content,
    ) {
    }

    public function label(): Label
    {
        return $this->label;
    }

    public function content(): string
    {
        return $this->content;
    }
}
