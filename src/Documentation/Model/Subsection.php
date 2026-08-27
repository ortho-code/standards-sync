<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Model;

/** One thematic slice of a section's scenarios; a null title renders the slice inline, without a heading of its own. */
final readonly class Subsection
{
    /** @param list<DeclarationGroup> $groups */
    public function __construct(
        private ?string $title,
        private array $groups,
    ) {
    }

    public function title(): ?string
    {
        return $this->title;
    }

    /** @return list<DeclarationGroup> */
    public function groups(): array
    {
        return $this->groups;
    }
}
