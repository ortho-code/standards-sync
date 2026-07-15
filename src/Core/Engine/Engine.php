<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Engine;

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Plan\Plan;
use AlleKnalle\StandardsSync\Core\Render\BlockRenderer;
use AlleKnalle\StandardsSync\Core\Resolve\ContentMergerRegistry;
use AlleKnalle\StandardsSync\Core\Resolve\FileSpecResolver;

/** Computes a plan from config and applies it; the only writer in the pipeline. */
final readonly class Engine
{
    public function __construct(
        private FileSpecResolver $resolver,
        private Differ $differ,
        private Filesystem $filesystem,
    ) {
    }

    /** Wires the pure pipeline around a filesystem port; the caller supplies the adapter (the composition root). */
    public static function create(Filesystem $filesystem): self
    {
        return new self(
            new FileSpecResolver(ContentMergerRegistry::default()),
            new Differ($filesystem, [new BlockRenderer()]),
            $filesystem,
        );
    }

    public function plan(SyncConfig $config): Plan
    {
        $changes = [];
        foreach ($this->resolver->resolve($config) as $desiredFile) {
            $changes[] = $this->differ->diff($desiredFile);
        }

        return new Plan($changes);
    }

    public function apply(Plan $plan): void
    {
        foreach ($plan->drift() as $change) {
            $this->filesystem->write($change->path(), $change->desired());
        }
    }
}
