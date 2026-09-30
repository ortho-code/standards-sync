<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;

/** The parts of GitHub's workflow syntax the family reads: where a workflow's jobs, their steps, their references and their runners are, and what names a step. */
final readonly class WorkflowSyntax
{
    public const string KEY_JOBS = 'jobs';

    public const string KEY_STEPS = 'steps';

    /** A step's identifier, unique within its job's steps. */
    public const string KEY_ID = 'id';

    /** A step's action, or a job's reusable workflow, as a reference with a ref. */
    public const string KEY_USES = 'uses';

    /** The labels of the runner a job runs on. */
    public const string KEY_RUNS_ON = 'runs-on';

    /**
     * Whether a path leads to a job's steps.
     *
     * @param list<string|int> $path
     */
    public static function isStepsPath(array $path): bool
    {
        return count($path) === 3 && $path[0] === self::KEY_JOBS && is_string($path[1]) && $path[2] === self::KEY_STEPS;
    }

    /**
     * Whether a path leads to a step's action or a job's reusable workflow.
     *
     * @param list<string|int> $path
     */
    public static function isUsesPath(array $path): bool
    {
        $inJob = count($path) === 3 && $path[0] === self::KEY_JOBS && $path[2] === self::KEY_USES;
        $inStep = count($path) === 5 && $path[0] === self::KEY_JOBS && $path[2] === self::KEY_STEPS && is_int($path[3]) && $path[4] === self::KEY_USES;

        return $inJob || $inStep;
    }

    /**
     * Whether a path leads to a job's runner labels.
     *
     * @param list<string|int> $path
     */
    public static function isRunsOnPath(array $path): bool
    {
        return count($path) === 3 && $path[0] === self::KEY_JOBS && $path[2] === self::KEY_RUNS_ON;
    }

    /** The id a step declares, or null when it declares none. */
    public static function stepId(YamlValue $step): ?string
    {
        $mapping = $step->node();
        $id = $mapping instanceof YamlMapping ? $mapping->entry(self::KEY_ID)?->value()->decoded() : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
