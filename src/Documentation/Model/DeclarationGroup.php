<?php

declare(strict_types=1);

namespace StandardsSync\Documentation\Model;

/** The scenarios sharing one standards-sync.php declaration, with the declaration's source and what its rules report as. */
final readonly class DeclarationGroup
{
    /**
     * @param list<string> $reportLines
     * @param list<ScenarioEntry> $entries
     */
    public function __construct(
        private string $configSource,
        private array $reportLines,
        private array $entries,
    ) {
    }

    public function configSource(): string
    {
        return $this->configSource;
    }

    /** @return list<string> */
    public function reportLines(): array
    {
        return $this->reportLines;
    }

    /** @return list<ScenarioEntry> */
    public function entries(): array
    {
        return $this->entries;
    }
}
