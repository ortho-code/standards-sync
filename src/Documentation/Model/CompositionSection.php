<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Model;

/** One section of scenarios that no single rule owns — a cross-rule composition on a family page, or an engine-level behaviour. */
final readonly class CompositionSection
{
    /** @param list<Subsection> $subsections */
    public function __construct(
        private string $name,
        private array $subsections,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<Subsection> */
    public function subsections(): array
    {
        return $this->subsections;
    }
}
