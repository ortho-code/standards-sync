<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Model;

/** One family's catalog page: the names of its rule pages and its cross-rule composition scenarios. */
final readonly class FamilyPage
{
    /**
     * @param list<string> $ruleNames
     * @param list<CompositionSection> $sections
     */
    public function __construct(
        private string $family,
        private array $ruleNames,
        private array $sections,
    ) {
    }

    public function family(): string
    {
        return $this->family;
    }

    /** @return list<string> */
    public function ruleNames(): array
    {
        return $this->ruleNames;
    }

    /** @return list<CompositionSection> */
    public function sections(): array
    {
        return $this->sections;
    }
}
