<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Spec;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;

/**
 * One managed region declared by a rule set: which file, under which label, with what content.
 * Paths are relative; the resolver fans each spec across every configured root.
 */
final readonly class FileSpec
{
    public function __construct(
        private Path $relativePath,
        private Label $label,
        private string $content,
    ) {
    }

    public function relativePath(): Path
    {
        return $this->relativePath;
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
