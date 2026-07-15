<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Resolve;

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Plan\DesiredFile;
use AlleKnalle\StandardsSync\Core\Plan\ManagedBlock;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;

/**
 * Expands rule-set specs into desired files: one per (root, relative path).
 * Specs sharing a path are grouped by label, and each label's specs are merged into a single block.
 */
final readonly class FileSpecResolver
{
    public function __construct(private ContentMergerRegistry $mergers)
    {
    }

    /** @return list<DesiredFile> */
    public function resolve(SyncConfig $config): array
    {
        $specsByPath = $this->groupByPath($this->collectSpecs($config));

        $desiredFiles = [];
        foreach ($config->roots() as $root) {
            foreach ($specsByPath as $pathSpecs) {
                $desiredFiles[] = $this->buildDesiredFile($root, $pathSpecs);
            }
        }

        return $desiredFiles;
    }

    /** @return list<FileSpec> */
    private function collectSpecs(SyncConfig $config): array
    {
        $specs = [];
        foreach ($config->ruleSets() as $ruleSet) {
            foreach ($ruleSet->specs() as $spec) {
                $specs[] = $spec;
            }
        }

        return $specs;
    }

    /**
     * @param list<FileSpec> $specs
     * @return array<string, non-empty-list<FileSpec>>
     */
    private function groupByPath(array $specs): array
    {
        $byPath = [];
        foreach ($specs as $spec) {
            $byPath[$spec->relativePath()->value()][] = $spec;
        }

        return $byPath;
    }

    /** @param non-empty-list<FileSpec> $pathSpecs */
    private function buildDesiredFile(Path $root, array $pathSpecs): DesiredFile
    {
        $relativePath = $pathSpecs[0]->relativePath();
        $merger = $this->mergers->mergerFor($relativePath);

        $specsByLabel = [];
        foreach ($pathSpecs as $spec) {
            $specsByLabel[$spec->label()->value()][] = $spec;
        }

        $blocks = [];
        foreach ($specsByLabel as $labelSpecs) {
            $first = $labelSpecs[0];
            $blocks[] = new ManagedBlock($first->label(), $merger->merge($labelSpecs));
        }

        return new DesiredFile($root->join($relativePath), $blocks);
    }
}
