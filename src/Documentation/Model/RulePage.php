<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Model;

/** One rule's catalog page: its family (the index's grouping key), name, general description, and its scenarios. */
final readonly class RulePage
{
    /** @param list<Subsection> $subsections */
    public function __construct(
        private string $family,
        private string $name,
        private string $description,
        private array $subsections,
    ) {}

    public function family(): string
    {
        return $this->family;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /** @return list<Subsection> */
    public function subsections(): array
    {
        return $this->subsections;
    }
}
