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
 * Action references and runner labels are minimums; every other value is exact, compared in the spelling GitHub reads all its spellings of it as.
 * Only once the project holds everything declared does the walk look for what a standard stopped declaring, outermost first, so a retired node has its declared siblings beside it by the time it goes.
 * Places are spelled as paths of keys, a step by its id in brackets: `jobs.checks.steps[setup-php].with.php-version`.
 */
final readonly class WorkflowContainment
{
    /** A string that reads back as itself unquoted: no indicator first, nothing that ends a plain scalar inside. */
    private const string PLAIN_ITEM = '/^[A-Za-z0-9_.\/][A-Za-z0-9_.\/*@+-]*$/';

    /** The words YAML reads as something other than a string when written plain. */
    private const array NOT_PLAIN_WORDS = ['true', 'false', 'null', '~', 'yes', 'no', 'on', 'off'];

    /**
     * @param bool $referencesByAction whether a reference holds a declared one when it names the same action, whatever its version
     * @param list<string> $retired the retired pointers, as strings
     */
    private function __construct(
        private YamlTree $declared,
        private bool $referencesByAction = false,
        private array $retired = [],
    ) {}

    /** @param list<WorkflowPointer> $retired nodes an earlier sync recorded and nothing declares now */
    public static function firstDifference(DeclaredWorkflow $declared, YamlTree $actual, array $retired = []): ?WorkflowDifference
    {
        $walk = new self($declared->tree(), false, array_map(static fn(WorkflowPointer $pointer): string => $pointer->toString(), $retired));
        $difference = $walk->mapping($declared->tree()->root(), $actual->root(), [], '');
        if ($difference instanceof WorkflowDifference) {
            return $difference;
        }

        usort($retired, static fn(WorkflowPointer $a, WorkflowPointer $b): int => $a->depth() <=> $b->depth());
        foreach ($retired as $pointer) {
            $retraction = $walk->retraction($pointer, $actual);
            if ($retraction instanceof WorkflowDifference) {
                return $retraction;
            }
        }

        return null;
    }

    /**
     * The edit that takes a retired node out of the project's workflow, or null where the workflow no longer has it.
     * A node that is the last one its holder has leaves the holder as the standard now declares it: an event back to no filters, a string back to the declared value.
     * A node inside a value written in brackets or braces is refused, except an item of a flow list on one line, which the writer can take out.
     */
    private function retraction(WorkflowPointer $pointer, YamlTree $actual): ?WorkflowDifference
    {
        $segments = $pointer->segments();
        $last = count($segments) - 1;
        $holder = $actual->root();
        $path = [];
        $place = '';
        foreach ($segments as $depth => $segment) {
            if ($holder instanceof YamlMapping) {
                $entry = $holder->entry($segment);
                if (!$entry instanceof YamlEntry) {
                    return null;
                }
                $entryPlace = $place === '' ? $segment : $place . '.' . $segment;
                if ($depth === $last) {
                    return count($holder->entries()) > 1
                        ? WorkflowDifference::retiredEntry($path, $entryPlace, $segment)
                        : $this->declaredInstead($pointer, $path, $place, '"' . $entryPlace . '"');
                }
                $value = $entry->value();
                $path = [...$path, $segment];
                $place = $entryPlace;
                $holder = $value->node() ?? $value;
                continue;
            }
            if ($holder instanceof YamlSequence && WorkflowSyntax::isStepsPath($path)) {
                /** @var int|null $index a list's keys are its positions */
                $index = array_find_key($holder->items(), static fn(YamlItem $item): bool => WorkflowSyntax::stepId($item->value()) === $segment);
                if ($index === null) {
                    return null;
                }
                if ($depth === $last) {
                    return count($holder->items()) > 1
                        ? WorkflowDifference::retiredStep($path, $segments[1], $segment, $index)
                        : $this->declaredInstead($pointer, $path, $place, '"' . $place . '[' . $segment . ']"');
                }
                $holder = $holder->items()[$index]->value()->node();
                $path = [...$path, $index];
                $place .= '[' . $segment . ']';
                continue;
            }
            if ($holder instanceof YamlValue && $depth < $last) {
                $named = self::namedInside(WorkflowSyntax::canonical($path, $holder->decoded()), $segment, array_slice($segments, $depth + 1), $place);

                return $named === null ? null : WorkflowDifference::retiredInline($named, $place);
            }

            return $depth === $last && ($holder instanceof YamlSequence || $holder instanceof YamlValue)
                ? $this->retiredItem($holder, $pointer, $path, $place, $segment)
                : null;
        }

        return null;
    }

    /**
     * A retired scalar item of a list, block or spelled without nodes: where it is the last, the list takes the declared value; where the list has more, the item is removed from a block list or a flow list on one line, and refused in any other spelling, which the writer cannot edit.
     *
     * @param list<string|int> $path
     */
    private function retiredItem(YamlSequence|YamlValue $list, WorkflowPointer $pointer, array $path, string $place, string $segment): ?WorkflowDifference
    {
        $items = $list instanceof YamlSequence
            ? array_map(static fn(YamlItem $item): mixed => $item->value()->node() === null ? $item->value()->decoded() : null, $list->items())
            : WorkflowSyntax::canonical($path, $list->decoded());
        if (!is_array($items)) {
            return null;
        }
        $values = array_is_list($items) ? $items : array_keys($items);
        $matches = static fn(mixed $item): bool => is_scalar($item) && WorkflowPointer::segmentOf($item) === $segment;
        if (!array_any($values, $matches)) {
            return null;
        }
        $spelled = $list instanceof YamlValue ? $list->decoded() : $values;
        if (count($values) === 1 || !is_array($spelled)) {
            return $this->declaredInstead($pointer, $path, $place, self::itemsIn([$segment], $place));
        }
        if ($list instanceof YamlValue && !$list->isOneLineFlowSequence()) {
            return WorkflowDifference::retiredInline(array_is_list($items) ? self::itemsIn([$segment], $place) : '"' . $place . '.' . $segment . '"', $place);
        }

        return WorkflowDifference::retiredItem($path, $place, array_find($spelled, $matches), $segment);
    }

    /**
     * How an explanation names the node a pointer's remaining segments lead to inside a value spelled without nodes, or null where the value does not hold it.
     *
     * @param list<string> $deeper the segments after this one
     */
    private static function namedInside(mixed $decoded, string $segment, array $deeper, string $place): ?string
    {
        $held = is_array($decoded) ? $decoded : [$decoded];
        if (!array_is_list($held)) {
            if (!array_key_exists($segment, $held)) {
                return null;
            }
            $at = $place . '.' . $segment;

            return $deeper === [] ? '"' . $at . '"' : self::namedInside($held[$segment], $deeper[0], array_slice($deeper, 1), $at);
        }
        $holds = array_any($held, static fn(mixed $item): bool => is_scalar($item) && WorkflowPointer::segmentOf($item) === $segment);

        return $deeper === [] && $holds ? self::itemsIn([$segment], $place) : null;
    }

    /**
     * The retired node is the last one its holder has: the holder takes the value the standard declares for it now, which the declared workflow keeps valid.
     *
     * @param list<string|int> $holderPath where the holder's own entry stands in the project's workflow
     * @param string $named the retired node as the explanation names it
     */
    private function declaredInstead(WorkflowPointer $retired, array $holderPath, string $holderPlace, string $named): WorkflowDifference
    {
        $holder = $retired->parent();
        $declared = $holder instanceof WorkflowPointer ? $this->declaredEntry($holder) : null;

        return $declared instanceof YamlEntry && $holderPath !== []
            ? WorkflowDifference::retiredLast($holderPath, $holderPlace, $named, $this->declared, $declared)
            : WorkflowDifference::unretractable($named);
    }

    /**
     * Items of a list, or keys of a mapping spelled without nodes, as an explanation names them: `master in "on.push.branches"`.
     *
     * @param non-empty-list<string> $items
     */
    private static function itemsIn(array $items, string $place): string
    {
        return implode(', ', $items) . ' in "' . $place . '"';
    }

    /** The declared entry a pointer leads to, found by the same keys and step ids a project's workflow is searched by. */
    private function declaredEntry(WorkflowPointer $pointer): ?YamlEntry
    {
        $holder = $this->declared->root();
        $path = [];
        $entry = null;
        foreach ($pointer->segments() as $segment) {
            if ($holder instanceof YamlMapping) {
                $entry = $holder->entry($segment);
                $holder = $entry?->value()->node();
                $path[] = $segment;
                continue;
            }
            if ($holder instanceof YamlSequence && WorkflowSyntax::isStepsPath($path)) {
                $step = array_find($holder->items(), static fn(YamlItem $item): bool => WorkflowSyntax::stepId($item->value()) === $segment);
                $holder = $step?->value()->node();
                $entry = null;
                $path[] = 0;
                continue;
            }

            return null;
        }

        return $entry;
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
        if (self::holdsNothing($held)) {
            return WorkflowDifference::changedValue($path, $place, $this->declared, $declared);
        }

        if (WorkflowSyntax::isUsesPath($path) && self::isSingleLineScalar($wanted) && self::isSingleLineScalar($held)) {
            return $this->reference($wanted, $held, $path, $place);
        }
        if (WorkflowSyntax::isRunsOnPath($path)) {
            return $this->runner($declared, $held, $path, $place);
        }

        $wantedNode = $wanted->node();
        $heldNode = $held->node();
        if ($wantedNode instanceof YamlMapping) {
            return $heldNode instanceof YamlMapping
                ? $this->mapping($wantedNode, $heldNode, $path, $place)
                : $this->spelledMapping($declared, $held, $path, $place);
        }
        if ($wantedNode instanceof YamlSequence && WorkflowSyntax::isStepsPath($path)) {
            return $heldNode instanceof YamlSequence
                ? $this->steps($wantedNode, $heldNode, $path, $place)
                : WorkflowDifference::unwritableShape($place, self::shape($held), 'a list of steps');
        }
        $items = self::listItems($wanted, $path);
        if ($items !== null) {
            return $this->scalarList($declared, $items, $held, $path, $place);
        }

        return $this->exact($declared, $held, $path, $place);
    }

    /**
     * A declared mapping the project spells another way GitHub reads the same: triggers as a list, an environment as its name.
     * Where the spelling holds what the mapping declares, it passes; where not, a string the declared mapping stands for is replaced by it, and triggers are refused, since rewriting them would lose the project's own — unless every key the spelling holds is one the standard retired, which leaves nothing of the project's to keep, so the declared value takes its place.
     *
     * @param non-empty-list<string|int> $path
     */
    private function spelledMapping(YamlEntry $declared, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $spelled = WorkflowSyntax::canonical($path, $held->decoded());
        if (self::contains($declared->value()->decoded(), $spelled)) {
            return null;
        }
        if (WorkflowSyntax::takesStringAsMapping($path) && self::isSingleLineScalar($held)) {
            return WorkflowDifference::changedValue($path, $place, $this->declared, $declared);
        }

        $keys = is_array($spelled) ? array_map(strval(...), array_keys($spelled)) : [];

        return $keys !== [] && !array_any($keys, fn(string $key): bool => !$this->isRetiredItem($path, $key))
            ? WorkflowDifference::retiredLast($path, $place, self::itemsIn($keys, $place), $this->declared, $declared)
            : WorkflowDifference::unwritableShape($place, self::shape($held), 'a mapping');
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
     * A job's runner labels: each declared label met by one of the project's of its name and suffix at its version or later, whether either side writes one label or a list; the project's own labels beside it stay.
     *
     * @param non-empty-list<string|int> $path
     */
    private function runner(YamlEntry $declared, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $wanted = $declared->value();
        $minimums = self::strings(WorkflowSyntax::canonical($path, $wanted->decoded()));
        $labels = self::strings(WorkflowSyntax::canonical($path, $held->decoded()));
        if ($minimums === null || $labels === null) {
            return $this->exact($declared, $held, $path, $place);
        }
        $met = !array_any($minimums, static fn(string $minimum): bool => !array_any(
            $labels,
            static fn(string $label): bool => $label === $minimum || RunnerLabel::fromString($label)->isAtLeast(RunnerLabel::fromString($minimum)) === true,
        ));
        if ($met) {
            return null;
        }
        if (!self::isSingleLineScalar($wanted) || !self::isSingleLineScalar($held)) {
            return WorkflowDifference::changedValue($path, $place, $this->declared, $declared);
        }

        $minimum = RunnerLabel::fromString($minimums[0]);
        $actual = RunnerLabel::fromString($labels[0]);
        $atLeast = $actual->isAtLeast($minimum);

        return $atLeast === null || $actual->isSameKind($minimum)
            ? WorkflowDifference::belowMinimum($path, $place, $held->source(), $wanted->source(), $atLeast === false, true)
            : WorkflowDifference::changedScalar($path, $place, $held->source(), $wanted->source());
    }

    /**
     * A declared list of scalars, or a single one GitHub reads as a list: each must be among the project's.
     * A missing one is appended to the project's list; a project writing a single value where more are declared is refused, since the value cannot take a second — unless that value is one the standard retired, which leaves nothing of the project's to keep, so the declared value takes its place.
     *
     * @param list<array{mixed, string}> $items each declared item, decoded, and as its source reads
     * @param non-empty-list<string|int> $path
     */
    private function scalarList(YamlEntry $declared, array $items, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $present = WorkflowSyntax::canonical($path, $held->decoded());
        if (!is_array($present) || !array_is_list($present)) {
            return WorkflowDifference::unwritableShape($place, self::shape($held), 'a list');
        }
        foreach ($items as [$item, $source]) {
            if (in_array($item, $present, true)) {
                continue;
            }
            if (is_array($held->decoded())) {
                return WorkflowDifference::missingListItem($path, $place, $source);
            }

            return $this->isRetiredItem($path, $held->decoded())
                ? WorkflowDifference::retiredLast($path, $place, self::itemsIn([WorkflowPointer::segmentOf($held->decoded())], $place), $this->declared, $declared)
                : WorkflowDifference::unwritableShape($place, 'a single value', 'a list');
        }

        return null;
    }

    /**
     * Whether a list's item at a path, or a key of a mapping spelled without nodes, is one the standard retired; a path inside a step holds its position rather than its id, so nothing there reads as retired.
     *
     * @param non-empty-list<string|int> $path
     */
    private function isRetiredItem(array $path, mixed $item): bool
    {
        $segments = array_map(strval(...), $path);

        return in_array(WorkflowPointer::fromSegments([...$segments, WorkflowPointer::segmentOf($item)])->toString(), $this->retired, true);
    }

    /** @param non-empty-list<string|int> $path */
    private function exact(YamlEntry $declared, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $wanted = $declared->value();
        $canonicalWanted = WorkflowSyntax::canonical($path, $wanted->decoded());
        $canonicalHeld = WorkflowSyntax::canonical($path, $held->decoded());
        if (WorkflowSyntax::takesStringAsMapping($path) ? self::contains($canonicalWanted, $canonicalHeld) : $canonicalWanted === $canonicalHeld) {
            return null;
        }

        return self::isSingleLineScalar($wanted) && self::isSingleLineScalar($held)
            ? WorkflowDifference::changedScalar($path, $place, $held->source(), $wanted->source())
            : WorkflowDifference::changedValue($path, $place, $this->declared, $declared);
    }

    /**
     * The items of a declared value held as a list: a block list of scalars, or a single scalar where GitHub reads one as a list; null for any other value.
     *
     * @param non-empty-list<string|int> $path
     * @return list<array{mixed, string}>|null each item decoded, and as its source reads
     */
    private static function listItems(YamlValue $wanted, array $path): ?array
    {
        $node = $wanted->node();
        if ($node instanceof YamlSequence) {
            $items = array_map(static fn(YamlItem $item): array => [$item->value()->decoded(), $item->value()->source()], $node->items());

            return array_any($items, static fn(array $item): bool => is_array($item[0])) ? null : $items;
        }
        $decoded = $wanted->decoded();
        if ($wanted->kind() === YamlValueKind::Flow && is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
            $items = [];
            foreach ($decoded as $item) {
                $written = self::written($item);
                if ($written === null) {
                    return null;
                }
                $items[] = [$item, $written];
            }

            return $items;
        }

        return WorkflowSyntax::takesStringAsList($path) && self::isSingleLineScalar($wanted) ? [[$decoded, $wanted->source()]] : null;
    }

    /** A flow list's item as a block list item writes it: plain where it reads back as itself, single-quoted where not; null for anything but a string or an integer. */
    private static function written(mixed $item): ?string
    {
        if (is_int($item)) {
            return (string) $item;
        }
        if (!is_string($item)) {
            return null;
        }

        return preg_match(self::PLAIN_ITEM, $item) === 1 && !is_numeric($item) && !in_array(strtolower($item), self::NOT_PLAIN_WORDS, true)
            ? $item
            : '\'' . str_replace('\'', '\'\'', $item) . '\'';
    }

    /** Whether a decoded value holds a declared one: every declared key present, a declared key with nothing under it asking for the key alone, every declared list item among the held ones, every scalar equal. */
    private static function contains(mixed $declared, mixed $held): bool
    {
        if (!is_array($declared)) {
            return $declared === $held;
        }
        if (!is_array($held)) {
            return false;
        }
        if (array_is_list($declared)) {
            return !array_any($declared, static fn(mixed $item): bool => !in_array($item, $held, true));
        }

        return !array_any(
            array_keys($declared),
            static fn(int|string $key): bool => !array_key_exists($key, $held) || ($declared[$key] !== null && !self::contains($declared[$key], $held[$key])),
        );
    }

    /** Whether the project's value holds nothing: no value at all, `~`, or an empty `{}` or `[]`. */
    private static function holdsNothing(YamlValue $value): bool
    {
        if ($value->kind() === YamlValueKind::Empty) {
            return true;
        }

        return $value->node() === null && in_array($value->decoded(), [null, []], true);
    }

    /**
     * @return list<string>|null
     */
    private static function strings(mixed $value): ?array
    {
        return is_array($value) && array_is_list($value) && !array_any($value, static fn(mixed $item): bool => !is_string($item)) ? $value : null;
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
