<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Model;

/** One documented scenario: its heading sentence, the fixture directory it renders (project-relative), and its file examples. */
final readonly class ScenarioEntry
{
    /** @param list<FileExample> $examples */
    public function __construct(
        private string $heading,
        private string $fixtureDirectory,
        private array $examples,
    ) {
    }

    public function heading(): string
    {
        return $this->heading;
    }

    public function fixtureDirectory(): string
    {
        return $this->fixtureDirectory;
    }

    /** @return list<FileExample> */
    public function examples(): array
    {
        return $this->examples;
    }
}
