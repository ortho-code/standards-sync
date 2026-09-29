<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Engine;

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Filesystem\Filesystem;
use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Plan\Abstention;
use OrthoCode\StandardsSync\Core\Plan\Change;
use OrthoCode\StandardsSync\Core\Plan\ChangeKind;
use OrthoCode\StandardsSync\Core\Plan\Plan;
use OrthoCode\StandardsSync\Core\Plan\RuleApplication;
use OrthoCode\StandardsSync\Core\Rule\AppliesAtPath;
use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use RuntimeException;

/**
 * Computes a plan from config and applies it; the only writer in the pipeline.
 * Planning folds each file's rules in declaration order over the current content: one read and one Change per file, and rules never touch the filesystem.
 */
final readonly class Engine
{
    private TargetResolver $targetResolver;

    public function __construct(private Filesystem $filesystem)
    {
        $this->targetResolver = new TargetResolver($filesystem);
    }

    public function plan(SyncConfig $config): Plan
    {
        $rules = $this->mergeContributions($this->collectRules($config));

        $outcomes = [];
        foreach ($config->roots() as $root) {
            array_push($outcomes, ...$this->outcomesFor($root, $rules));
        }

        return new Plan(
            array_values(array_filter($outcomes, static fn(Change|Abstention $outcome): bool => $outcome instanceof Change)),
            array_values(array_filter($outcomes, static fn(Change|Abstention $outcome): bool => $outcome instanceof Abstention)),
        );
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
     * Contributions to one list in one target merge into the first of them through the rule's own withMerged(), so the fold sees one rule per list.
     *
     * @param list<Rule> $rules
     * @return list<Rule>
     */
    private function mergeContributions(array $rules): array
    {
        $merged = [];
        /** @var array<string, array<string, array<string, int>>> $positions */
        $positions = [];
        foreach ($rules as $rule) {
            if ($rule instanceof ContributesToList) {
                $position = $positions[$rule::class][$rule->target()->toString()][$rule->listKey()] ?? null;
                $earlier = $position === null ? null : $merged[$position];
                if ($position !== null && $earlier instanceof ContributesToList) {
                    /** @var Rule&ContributesToList $combined psalm does not bind static to the intersection it was called on */
                    $combined = $earlier->withMerged($rule);
                    $merged[$position] = $combined;
                    continue;
                }

                $positions[$rule::class][$rule->target()->toString()][$rule->listKey()] = count($merged);
            }

            $merged[] = $rule;
        }

        return array_values($merged);
    }

    /**
     * @param list<Rule> $rules
     * @return list<Change|Abstention>
     */
    private function outcomesFor(Path $root, array $rules): array
    {
        // Group by resolved path, so rules with different candidate lists that resolve to the same file fold together.
        /** @var array<string, ResolvedTarget> $targets */
        $targets = [];
        /** @var array<string, non-empty-list<Rule>> $rulesByPath */
        $rulesByPath = [];
        foreach ($rules as $rule) {
            $target = $this->targetResolver->resolve($root, $rule->target());
            $key = $target->path()->value();
            $targets[$key] ??= $target;
            $rulesByPath[$key][] = $rule;
        }

        $outcomes = [];
        foreach ($rulesByPath as $key => $fileRules) {
            $outcomes[] = $this->foldFile($targets[$key], $fileRules);
        }

        return $outcomes;
    }

    /**
     * Folds the file's rules in declaration order over the current content; the change kind derives from the fold's endpoints.
     *
     * @param non-empty-list<Rule> $rules
     */
    private function foldFile(ResolvedTarget $target, array $rules): Change|Abstention
    {
        $current = $target->current();

        $applications = [];
        $content = $current;
        foreach ($rules as $rule) {
            $before = $content;
            $content = $rule instanceof AppliesAtPath ? $rule->applyAt($target->path(), $before) : $rule->apply($before);
            $applications[] = new RuleApplication($rule, $before, $content);
        }

        // Every rule abstained on an absent file: nothing exists and nothing should, and the rules that stood down are told rather than dropped.
        if ($current === null && $content === null) {
            return new Abstention($target->path(), $rules);
        }

        if ($content === null) {
            throw new RuntimeException(sprintf('The rules for "%s" want the file deleted, but deletion is not supported.', $target->path()->value()));
        }

        $kind = match (true) {
            $current === null => ChangeKind::Create,
            $current === $content => ChangeKind::InSync,
            default => ChangeKind::Update,
        };

        return new Change($target->path(), $kind, $current, $content, $applications, $target->shadowedBy());
    }
}
