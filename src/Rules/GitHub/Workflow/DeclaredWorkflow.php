<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use InvalidArgumentException;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValueKind;
use RuntimeException;

/** The workflow a standard declares: its source as written, and its tree, every step carrying an id no other step of its job has. */
final readonly class DeclaredWorkflow
{
    private function __construct(
        private string $source,
        private YamlTree $tree,
    ) {}

    /**
     * The workflow is validated rather than trusted: an org package is plain PHP, and a step without an id could never be told apart from a project's own.
     * An empty node GitHub rejects is refused too, since a sync retracting what a standard stopped declaring would have to write it.
     *
     * @throws InvalidArgumentException for a workflow the reader refuses, no jobs, a job with its steps key but no steps, a filter with no patterns, a step that is not a mapping, and a step without an id or with its job's other step's
     */
    public static function fromString(string $workflow): self
    {
        try {
            $tree = YamlTree::fromString($workflow);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException(sprintf('The declared workflow cannot be read: %s', $exception->getMessage()), 0, $exception);
        }

        $declaredJobs = $tree->valueAt([WorkflowSyntax::KEY_JOBS]);
        if ($declaredJobs instanceof YamlValue && self::isEmpty($declaredJobs)) {
            throw new InvalidArgumentException('The declared workflow has a jobs key but no jobs.');
        }
        $events = $tree->valueAt([WorkflowSyntax::KEY_ON])?->node();
        foreach ($events instanceof YamlMapping ? $events->entries() : [] as $event) {
            $filters = $event->value()->node();
            foreach ($filters instanceof YamlMapping ? $filters->entries() : [] as $filter) {
                if (WorkflowSyntax::takesStringAsList([WorkflowSyntax::KEY_ON, $event->key(), $filter->key()]) && self::isEmpty($filter->value())) {
                    throw new InvalidArgumentException(sprintf('The declared event "%s" has a %s filter with no patterns.', $event->key(), $filter->key()));
                }
            }
        }

        $jobs = $declaredJobs?->node();
        foreach ($jobs instanceof YamlMapping ? $jobs->entries() : [] as $job) {
            $declaredSteps = $job->value()->node() instanceof YamlMapping ? $tree->valueAt([WorkflowSyntax::KEY_JOBS, $job->key(), WorkflowSyntax::KEY_STEPS]) : null;
            if ($declaredSteps instanceof YamlValue && self::isEmpty($declaredSteps)) {
                throw new InvalidArgumentException(sprintf('The declared job "%s" has a steps key but no steps.', $job->key()));
            }
            $steps = $declaredSteps?->node();
            $ids = [];
            foreach ($steps instanceof YamlSequence ? $steps->items() : [] as $index => $step) {
                if (!$step->value()->node() instanceof YamlMapping) {
                    throw new InvalidArgumentException(sprintf('The declared step %d of the job "%s" is not a mapping.', $index + 1, $job->key()));
                }
                $id = WorkflowSyntax::stepId($step->value())
                    ?? throw new InvalidArgumentException(sprintf('The declared step %d of the job "%s" has no id; every declared step needs one, so a project\'s own steps can be told apart from it.', $index + 1, $job->key()));
                if (in_array($id, $ids, true)) {
                    throw new InvalidArgumentException(sprintf('The job "%s" declares the step id "%s" twice.', $job->key(), $id));
                }
                $ids[] = $id;
            }
        }

        return new self($workflow, $tree);
    }

    /** The workflow as written, comments included: what a project without the file gets. */
    public function source(): string
    {
        return $this->source;
    }

    public function tree(): YamlTree
    {
        return $this->tree;
    }

    /** How many entries and items the workflow declares, which bounds how many edits a sync can need. */
    public function nodeCount(): int
    {
        return self::count($this->tree->root());
    }

    /**
     * Every node the workflow declares, in declared order: each key, each of a job's steps by id, each scalar item of a list by value.
     *
     * @return non-empty-list<string>
     */
    public function pointers(): array
    {
        /** @var non-empty-list<string> $pointers a declared workflow's root mapping has at least one key */
        $pointers = self::mappingPointers($this->tree->root(), [], null);

        return $pointers;
    }

    /**
     * @param list<string|int> $path
     * @return list<string>
     */
    private static function mappingPointers(YamlMapping $mapping, array $path, ?WorkflowPointer $at): array
    {
        $pointers = [];
        foreach ($mapping->entries() as $entry) {
            $pointer = $at instanceof WorkflowPointer ? $at->to($entry->key()) : WorkflowPointer::fromSegments([$entry->key()]);
            $pointers[] = $pointer->toString();
            array_push($pointers, ...self::valuePointers($entry->value(), [...$path, $entry->key()], $pointer));
        }

        return $pointers;
    }

    /**
     * @param non-empty-list<string|int> $path
     * @return list<string>
     */
    private static function valuePointers(YamlValue $value, array $path, WorkflowPointer $at): array
    {
        $node = $value->node();
        if ($node instanceof YamlMapping) {
            return self::mappingPointers($node, $path, $at);
        }
        if ($node instanceof YamlSequence && WorkflowSyntax::isStepsPath($path)) {
            $pointers = [];
            foreach ($node->items() as $index => $step) {
                $pointer = $at->to((string) WorkflowSyntax::stepId($step->value()));
                $pointers[] = $pointer->toString();
                $mapping = $step->value()->node();
                if ($mapping instanceof YamlMapping) {
                    array_push($pointers, ...self::mappingPointers($mapping, [...$path, $index], $pointer));
                }
            }

            return $pointers;
        }

        return self::decodedPointers(WorkflowSyntax::canonical($path, $value->decoded()), $at);
    }

    /**
     * The pointers inside a value spelled without nodes of its own: a flow or string list's scalar items, a flow or string spelling's keys.
     *
     * @return list<string>
     */
    private static function decodedPointers(mixed $canonical, WorkflowPointer $at): array
    {
        if (!is_array($canonical)) {
            return [];
        }
        if (array_is_list($canonical)) {
            return array_any($canonical, static fn(mixed $item): bool => !is_scalar($item))
                ? []
                : array_map(static fn(mixed $item): string => $at->to(WorkflowPointer::segmentOf($item))->toString(), $canonical);
        }
        $pointers = [];
        foreach ($canonical as $key => $nested) {
            $pointer = $at->to((string) $key);
            $pointers[] = $pointer->toString();
            array_push($pointers, ...self::decodedPointers($nested, $pointer));
        }

        return $pointers;
    }

    private static function isEmpty(YamlValue $value): bool
    {
        return $value->kind() === YamlValueKind::Empty || ($value->node() === null && in_array($value->decoded(), [null, []], true));
    }

    private static function count(YamlMapping|YamlSequence $node): int
    {
        $count = 0;
        foreach ($node instanceof YamlMapping ? $node->entries() : $node->items() as $child) {
            $nested = $child->value()->node();
            $count += 1 + ($nested === null ? 0 : self::count($nested));
        }

        return $count;
    }
}
