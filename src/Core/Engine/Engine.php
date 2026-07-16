<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Engine;

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Plan\Change;
use AlleKnalle\StandardsSync\Core\Plan\ChangeKind;
use AlleKnalle\StandardsSync\Core\Plan\Plan;
use AlleKnalle\StandardsSync\Core\Plan\RuleApplication;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use RuntimeException;

/**
 * Computes a plan from config and applies it; the only writer in the pipeline.
 * Planning folds each file's rules in declaration order over the current content: one read and one Change per file, and rules never touch the filesystem.
 */
final readonly class Engine
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function plan(SyncConfig $config): Plan
    {
        $rules = $this->collectRules($config);

        $changes = [];
        foreach ($config->roots() as $root) {
            array_push($changes, ...$this->changesFor($root, $rules));
        }

        return new Plan($changes);
    }

    public function apply(Plan $plan): void
    {
        foreach ($plan->drift() as $change) {
            $this->filesystem->write($change->path(), $change->desired());
        }
    }

    /** @return list<Rule> */
    private function collectRules(SyncConfig $config): array
    {
        $rules = [];
        foreach ($config->ruleSets() as $ruleSet) {
            foreach ($ruleSet->rules() as $rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param list<Rule> $rules
     * @return list<Change>
     */
    private function changesFor(Path $root, array $rules): array
    {
        // Group by resolved path, so rules with different candidate lists that resolve to the same file fold together.
        /** @var array<string, ResolvedTarget> $targets */
        $targets = [];
        /** @var array<string, non-empty-list<Rule>> $rulesByPath */
        $rulesByPath = [];
        foreach ($rules as $rule) {
            $target = $this->resolveTarget($root, $rule->target());
            $key = $target->path()->value();
            $targets[$key] ??= $target;
            $rulesByPath[$key][] = $rule;
        }

        $changes = [];
        foreach ($rulesByPath as $key => $fileRules) {
            $change = $this->foldFile($targets[$key], $fileRules);
            if ($change instanceof Change) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /** Resolves a target to the first candidate that exists under the root, or the first candidate if none do. */
    private function resolveTarget(Path $root, FileTarget $target): ResolvedTarget
    {
        foreach ($target->candidates() as $candidate) {
            $path = $root->join($candidate);
            $current = $this->filesystem->read($path);
            if ($current !== null) {
                return new ResolvedTarget($path, $current);
            }
        }

        return new ResolvedTarget($root->join($target->candidates()[0]), null);
    }

    /**
     * Folds the file's rules in declaration order over the current content; the change kind derives from the fold's endpoints.
     *
     * @param non-empty-list<Rule> $rules
     */
    private function foldFile(ResolvedTarget $target, array $rules): ?Change
    {
        $current = $target->current();

        $applications = [];
        $content = $current;
        foreach ($rules as $rule) {
            $before = $content;
            $content = $rule->apply($before);
            $applications[] = new RuleApplication($rule, $before, $content);
        }

        // Every rule abstained on an absent file: nothing exists and nothing should.
        if ($current === null && $content === null) {
            return null;
        }

        if ($content === null) {
            throw new RuntimeException(sprintf('The rules for "%s" want the file deleted, but deletion is not supported.', $target->path()->value()));
        }

        $kind = match (true) {
            $current === null => ChangeKind::Create,
            $current === $content => ChangeKind::InSync,
            default => ChangeKind::Update,
        };

        return new Change($target->path(), $kind, $current, $content, $applications);
    }
}
