<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValueKind;

/**
 * Walks the declared workflow against a project's, in declared order, and finds the first place the project falls short of it.
 * A declared key must be present and its value contained; a key with nothing under it asks for the key alone.
 * A job's steps are found by id, in declared order, a step without one adopted when it holds the declared step; a list of scalars must hold the declared ones.
 * Action references and runner labels are minimums; every other value is exact.
 * Places are spelled as paths of keys, a step by its id in brackets: `jobs.checks.steps[setup-php].with.php-version`.
 */
final readonly class WorkflowContainment
{
    /** @param bool $referencesByAction whether a reference holds a declared one when it names the same action, whatever its version */
    private function __construct(
        private YamlTree $declared,
        private bool $referencesByAction = false,
    ) {}

    public static function firstDifference(DeclaredWorkflow $declared, YamlTree $actual): ?WorkflowDifference
    {
        return new self($declared->tree())->mapping($declared->tree()->root(), $actual->root(), [], '');
    }

    /**
     * @param list<string|int> $path where the project's mapping stands
     * @param list<string> $ignoring declared keys to leave out
     */
    private function mapping(YamlMapping $declared, YamlMapping $actual, array $path, string $place, array $ignoring = []): ?WorkflowDifference
    {
        foreach ($declared->entries() as $entry) {
            if (in_array($entry->key(), $ignoring, true)) {
                continue;
            }
            $present = $actual->entry($entry->key());
            $difference = $present instanceof YamlEntry
                ? $this->entry($entry, $present, [...$path, $entry->key()], $place === '' ? $entry->key() : $place . '.' . $entry->key())
                : WorkflowDifference::missingEntry($path, $place, $this->declared, $entry);
            if ($difference instanceof WorkflowDifference) {
                return $difference;
            }
        }

        return null;
    }

    /** @param non-empty-list<string|int> $path */
    private function entry(YamlEntry $declared, YamlEntry $actual, array $path, string $place): ?WorkflowDifference
    {
        $wanted = $declared->value();
        $held = $actual->value();
        if ($wanted->kind() === YamlValueKind::Empty) {
            return null;
        }
        if ($held->kind() === YamlValueKind::Empty) {
            return WorkflowDifference::changedValue($path, $place, $this->declared, $declared);
        }

        if (WorkflowSyntax::isUsesPath($path) && self::isSingleLineScalar($wanted) && self::isSingleLineScalar($held)) {
            return $this->reference($wanted, $held, $path, $place);
        }
        if (WorkflowSyntax::isRunsOnPath($path) && self::isSingleLineScalar($wanted) && self::isSingleLineScalar($held)) {
            return $this->runner($wanted, $held, $path, $place);
        }

        $wantedNode = $wanted->node();
        $heldNode = $held->node();
        if ($wantedNode instanceof YamlMapping) {
            return $heldNode instanceof YamlMapping
                ? $this->mapping($wantedNode, $heldNode, $path, $place)
                : WorkflowDifference::unwritableShape($place, self::shape($held), 'a mapping');
        }
        if ($wantedNode instanceof YamlSequence && WorkflowSyntax::isStepsPath($path)) {
            return $heldNode instanceof YamlSequence
                ? $this->steps($wantedNode, $heldNode, $path, $place)
                : WorkflowDifference::unwritableShape($place, self::shape($held), 'a list of steps');
        }
        if ($wantedNode instanceof YamlSequence && self::holdsScalars($wantedNode)) {
            return $this->scalarList($wantedNode, $held, $path, $place);
        }

        return $this->exact($declared, $held, $path, $place);
    }

    /**
     * @param list<string|int> $path
     */
    private function steps(YamlSequence $declared, YamlSequence $actual, array $path, string $place): ?WorkflowDifference
    {
        $job = (string) $path[1];
        $items = $actual->items();
        $cursor = 0;
        $previous = null;
        foreach ($declared->items() as $step) {
            $id = WorkflowSyntax::stepId($step->value()) ?? '';
            /** @var int|null $index a list's keys are its positions */
            $index = array_find_key($items, static fn(YamlItem $item): bool => WorkflowSyntax::stepId($item->value()) === $id);
            if ($index === null) {
                $adoptable = $this->adoptable($step, $items, $path, $cursor);

                return $adoptable === null
                    ? WorkflowDifference::missingStep($path, $cursor, $job, $this->declared, $step, $id, $previous)
                    : WorkflowDifference::unidentifiedStep([...$path, $adoptable], $job, $id, self::idSource($step));
            }
            if ($index < $cursor && $previous !== null) {
                return WorkflowDifference::reorderedStep($job, $id, $previous);
            }

            $declaredMapping = $step->value()->node();
            $actualMapping = $items[$index]->value()->node();
            $stepPlace = $place . '[' . $id . ']';
            if (!$actualMapping instanceof YamlMapping) {
                return WorkflowDifference::unwritableShape($stepPlace, self::shape($items[$index]->value()), 'a mapping');
            }
            if ($declaredMapping instanceof YamlMapping) {
                $difference = $this->mapping($declaredMapping, $actualMapping, [...$path, $index], $stepPlace);
                if ($difference instanceof WorkflowDifference) {
                    return $difference;
                }
            }
            $cursor = $index + 1;
            $previous = $id;
        }

        return null;
    }

    /**
     * The step without an id to take as the declared step, its id aside: among the steps after the declared one before it, the first that holds it as it is, then the first that holds it with its action at any version; only then an earlier step, which leaves it out of order.
     * The version is set aside only to adopt: the next walk finds the step by its id and holds its action to the minimum.
     *
     * @param list<YamlItem> $items
     * @param list<string|int> $path
     */
    private function adoptable(YamlItem $step, array $items, array $path, int $cursor): ?int
    {
        $declared = $step->value()->node();
        if (!$declared instanceof YamlMapping) {
            return null;
        }
        foreach ([true, false] as $inPlace) {
            foreach ([false, true] as $referencesByAction) {
                $walk = new self($this->declared, $referencesByAction);
                foreach ($items as $index => $item) {
                    $candidate = $item->value()->node();
                    if (($index >= $cursor) === $inPlace && WorkflowSyntax::stepId($item->value()) === null && $candidate instanceof YamlMapping
                        && !$walk->mapping($declared, $candidate, [...$path, $index], '', [WorkflowSyntax::KEY_ID]) instanceof WorkflowDifference) {
                        return $index;
                    }
                }
            }
        }

        return null;
    }

    /**
     * An action reference: the declared one's action at its version or later.
     *
     * @param non-empty-list<string|int> $path
     */
    private function reference(YamlValue $wanted, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        if ($wanted->decoded() === $held->decoded()) {
            return null;
        }
        $minimum = ActionReference::fromUses((string) $wanted->decoded(), $wanted->comment());
        $actual = ActionReference::fromUses((string) $held->decoded(), $held->comment());
        if ($this->referencesByAction ? $actual->isSameAction($minimum) : $actual->isAtLeast($minimum) === true) {
            return null;
        }

        // A pin comment belongs to its reference: the project's goes with the value it named, and the declared one comes along with the declared value.
        $declared = $minimum->namesVersionInComment() ? $wanted->source() . ' #' . $wanted->comment() : $wanted->source();
        $keepComment = !$actual->namesVersionInComment() && !$minimum->namesVersionInComment();

        return $actual->isSameAction($minimum)
            ? WorkflowDifference::belowMinimum($path, $place, $held->source(), $declared, $actual->isAtLeast($minimum) === false, $keepComment)
            : WorkflowDifference::changedScalar($path, $place, $held->source(), $declared, $keepComment);
    }

    /**
     * A runner label: the declared label's name and suffix at its version or later.
     *
     * @param non-empty-list<string|int> $path
     */
    private function runner(YamlValue $wanted, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        if ($wanted->decoded() === $held->decoded()) {
            return null;
        }
        $minimum = RunnerLabel::fromString((string) $wanted->decoded());
        $actual = RunnerLabel::fromString((string) $held->decoded());
        $atLeast = $actual->isAtLeast($minimum);
        if ($atLeast === true) {
            return null;
        }

        return $atLeast === null || $actual->isSameKind($minimum)
            ? WorkflowDifference::belowMinimum($path, $place, $held->source(), $wanted->source(), $atLeast === false, true)
            : WorkflowDifference::changedScalar($path, $place, $held->source(), $wanted->source());
    }

    /** @param non-empty-list<string|int> $path */
    private function scalarList(YamlSequence $declared, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $present = $held->decoded();
        if (!is_array($present) || !array_is_list($present)) {
            return WorkflowDifference::unwritableShape($place, self::shape($held), 'a list');
        }
        foreach ($declared->items() as $item) {
            if (!in_array($item->value()->decoded(), $present, true)) {
                return WorkflowDifference::missingListItem($path, $place, $item->value()->source());
            }
        }

        return null;
    }

    /** @param non-empty-list<string|int> $path */
    private function exact(YamlEntry $declared, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $wanted = $declared->value();
        if ($wanted->decoded() === $held->decoded()) {
            return null;
        }

        return self::isSingleLineScalar($wanted) && self::isSingleLineScalar($held)
            ? WorkflowDifference::changedScalar($path, $place, $held->source(), $wanted->source())
            : WorkflowDifference::changedValue($path, $place, $this->declared, $declared);
    }

    private static function holdsScalars(YamlSequence $sequence): bool
    {
        return !array_any($sequence->items(), static fn(YamlItem $item): bool => $item->value()->node() !== null || is_array($item->value()->decoded()));
    }

    private static function isSingleLineScalar(YamlValue $value): bool
    {
        return in_array($value->kind(), [YamlValueKind::Plain, YamlValueKind::Quoted], true) && !$value->isMultiline();
    }

    private static function idSource(YamlItem $step): string
    {
        $mapping = $step->value()->node();
        $id = $mapping instanceof YamlMapping ? $mapping->entry(WorkflowSyntax::KEY_ID) : null;

        return $id instanceof YamlEntry ? $id->value()->source() : '';
    }

    /** What a value is, in the words a refusal uses. */
    private static function shape(YamlValue $value): string
    {
        $node = $value->node();
        if ($node !== null) {
            return $node instanceof YamlMapping ? 'a mapping' : 'a list';
        }
        $decoded = $value->decoded();
        if (!is_array($decoded)) {
            return 'a single value';
        }

        return array_is_list($decoded) ? 'a list' : 'a mapping';
    }
}
