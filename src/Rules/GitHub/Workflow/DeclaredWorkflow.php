<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use InvalidArgumentException;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
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
     *
     * @throws InvalidArgumentException for a workflow the reader refuses, a step that is not a mapping, and a step without an id or with its job's other step's
     */
    public static function fromString(string $workflow): self
    {
        try {
            $tree = YamlTree::fromString($workflow);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException(sprintf('The declared workflow cannot be read: %s', $exception->getMessage()), 0, $exception);
        }

        $jobs = $tree->root()->entry(WorkflowSyntax::KEY_JOBS)?->value()->node();
        foreach ($jobs instanceof YamlMapping ? $jobs->entries() : [] as $job) {
            $steps = $job->value()->node() instanceof YamlMapping ? $tree->valueAt([WorkflowSyntax::KEY_JOBS, $job->key(), WorkflowSyntax::KEY_STEPS])?->node() : null;
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
