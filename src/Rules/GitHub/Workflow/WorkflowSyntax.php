<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;

/** The parts of GitHub's workflow syntax the family reads: where a workflow's jobs and their steps are, and what names a step. */
final readonly class WorkflowSyntax
{
    public const string KEY_JOBS = 'jobs';

    public const string KEY_STEPS = 'steps';

    /** A step's identifier, unique within its job's steps. */
    public const string KEY_ID = 'id';

    /**
     * Whether a path leads to a job's steps.
     *
     * @param list<string|int> $path
     */
    public static function isStepsPath(array $path): bool
    {
        return count($path) === 3 && $path[0] === self::KEY_JOBS && is_string($path[1]) && $path[2] === self::KEY_STEPS;
    }

    /** The id a step declares, or null when it declares none. */
    public static function stepId(YamlValue $step): ?string
    {
        $mapping = $step->node();
        $id = $mapping instanceof YamlMapping ? $mapping->entry(self::KEY_ID)?->value()->decoded() : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
