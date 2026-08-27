<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Config;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\RuleSet\RuleSet;

/** Immutable configuration returned by a standards-sync.php: which roots to sync and which rule sets apply. */
final readonly class SyncConfig
{
    /** The root a config without withRoots() syncs: the directory the sync runs in. */
    private const string DEFAULT_ROOT = '.';

    /**
     * @param list<Path> $roots
     * @param list<RuleSet> $ruleSets
     */
    private function __construct(
        private array $roots,
        private array $ruleSets,
    ) {
    }

    public static function create(): self
    {
        return new self([Path::fromString(self::DEFAULT_ROOT)], []);
    }

    /** @param list<string> $roots */
    public function withRoots(array $roots): self
    {
        return new self(
            array_map(static fn (string $root): Path => Path::fromString($root), $roots),
            $this->ruleSets,
        );
    }

    public function withRuleSet(RuleSet $ruleSet): self
    {
        return new self($this->roots, [...$this->ruleSets, $ruleSet]);
    }

    /** @return list<Path> */
    public function roots(): array
    {
        return $this->roots;
    }

    /** @return list<RuleSet> */
    public function ruleSets(): array
    {
        return $this->ruleSets;
    }
}
