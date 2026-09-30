<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;

/**
 * The parts of GitHub's workflow syntax the family reads: where a workflow's jobs, their steps, their references and their runners are, what names a step, and the spellings GitHub reads as one another.
 * The equivalences are GitHub's own, from its workflow parser's converters: `on: push`, `on: [push]` and `on: {push: null}` are one trigger; a job's `needs` and `runs-on` and an event's filters read a single string as a list holding it; a job's `environment` reads a string as its name and its `container` as its image; a condition reads the same with or without `${{ }}` around it.
 */
final readonly class WorkflowSyntax
{
    public const string KEY_ON = 'on';

    public const string KEY_JOBS = 'jobs';

    public const string KEY_STEPS = 'steps';

    /** A step's identifier, unique within its job's steps. */
    public const string KEY_ID = 'id';

    /** A step's action, or a job's reusable workflow, as a reference with a ref. */
    public const string KEY_USES = 'uses';

    /** The labels of the runner a job runs on. */
    public const string KEY_RUNS_ON = 'runs-on';

    public const string KEY_NEEDS = 'needs';

    public const string KEY_IF = 'if';

    /** The keys a string stands for in a job's `environment` and `container`. */
    private const array STRING_KEYS = [
        'environment' => 'name',
        'container' => 'image',
    ];

    /** An event's filters, each a pattern or a list of them. */
    private const array FILTER_KEYS = ['branches', 'branches-ignore', 'tags', 'tags-ignore', 'paths', 'paths-ignore', 'types'];

    /** A condition wrapped in an expression, the condition captured. */
    private const string WRAPPED_CONDITION = '/^\$\{\{(.*)\}\}$/s';

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

    /**
     * Whether GitHub reads a single string at the path as a list holding it.
     *
     * @param list<string|int> $path
     */
    public static function takesStringAsList(array $path): bool
    {
        $jobKey = count($path) === 3 && $path[0] === self::KEY_JOBS && in_array($path[2], [self::KEY_NEEDS, self::KEY_RUNS_ON], true);
        $filter = count($path) === 3 && $path[0] === self::KEY_ON && in_array($path[2], self::FILTER_KEYS, true);

        return $jobKey || $filter;
    }

    /**
     * Whether GitHub reads a string at the path as one key of a mapping, as a job's `environment` and `container` do.
     *
     * @param list<string|int> $path
     */
    public static function takesStringAsMapping(array $path): bool
    {
        return count($path) === 3 && $path[0] === self::KEY_JOBS && is_string($path[2]) && isset(self::STRING_KEYS[$path[2]]);
    }

    /**
     * A value in the spelling GitHub reads all its equivalent spellings as: the triggers as a mapping, a string standing for a list or a mapping as that list or mapping, a condition without its `${{ }}`; any other value as it is.
     *
     * @param list<string|int> $path
     */
    public static function canonical(array $path, mixed $value): mixed
    {
        if ($path === [self::KEY_ON]) {
            return match (true) {
                is_string($value) => [
                    $value => null,
                ],
                is_array($value) && array_is_list($value) => array_fill_keys(array_filter($value, is_string(...)), null),
                default => $value,
            };
        }
        if (!is_string($value)) {
            return $value;
        }
        if (self::takesStringAsList($path)) {
            return [$value];
        }
        if (self::takesStringAsMapping($path)) {
            return [
                self::STRING_KEYS[(string) $path[2]] => $value,
            ];
        }
        if (self::isConditionPath($path) && preg_match(self::WRAPPED_CONDITION, trim($value), $match) === 1) {
            return trim($match[1]);
        }

        return $value;
    }

    /**
     * Whether a path leads to a job's or a step's condition.
     *
     * @param list<string|int> $path
     */
    private static function isConditionPath(array $path): bool
    {
        $inJob = count($path) === 3 && $path[0] === self::KEY_JOBS && $path[2] === self::KEY_IF;
        $inStep = count($path) === 5 && $path[0] === self::KEY_JOBS && $path[2] === self::KEY_STEPS && $path[4] === self::KEY_IF;

        return $inJob || $inStep;
    }

    /** The id a step declares, or null when it declares none. */
    public static function stepId(YamlValue $step): ?string
    {
        $mapping = $step->node();
        $id = $mapping instanceof YamlMapping ? $mapping->entry(self::KEY_ID)?->value()->decoded() : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
