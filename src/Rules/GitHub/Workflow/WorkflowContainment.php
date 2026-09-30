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
 * Places are spelled as paths of keys, a step by its id in brackets: `jobs.checks.steps[setup-php].with.php-version`.
 */
final readonly class WorkflowContainment
{
    /** A string that reads back as itself unquoted: no indicator first, nothing that ends a plain scalar inside. */
    private const string PLAIN_ITEM = '/^[A-Za-z0-9_.\/][A-Za-z0-9_.\/*@+-]*$/';

    /** The words YAML reads as something other than a string when written plain. */
    private const array NOT_PLAIN_WORDS = ['true', 'false', 'null', '~', 'yes', 'no', 'on', 'off'];

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
            return $this->scalarList($items, $held, $path, $place);
        }

        return $this->exact($declared, $held, $path, $place);
    }

    /**
     * A declared mapping the project spells another way GitHub reads the same: triggers as a list, an environment as its name.
     * Where the spelling holds what the mapping declares, it passes; where not, a string the declared mapping stands for is replaced by it, and triggers are refused, since rewriting them would lose the project's own.
     *
     * @param non-empty-list<string|int> $path
     */
    private function spelledMapping(YamlEntry $declared, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        if (self::contains($declared->value()->decoded(), WorkflowSyntax::canonical($path, $held->decoded()))) {
            return null;
        }

        return WorkflowSyntax::takesStringAsMapping($path) && self::isSingleLineScalar($held)
            ? WorkflowDifference::changedValue($path, $place, $this->declared, $declared)
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
     * A missing one is appended to the project's list; a project writing a single value where more are declared is refused, since the value cannot take a second.
     *
     * @param list<array{mixed, string}> $items each declared item, decoded, and as its source reads
     * @param non-empty-list<string|int> $path
     */
    private function scalarList(array $items, YamlValue $held, array $path, string $place): ?WorkflowDifference
    {
        $present = WorkflowSyntax::canonical($path, $held->decoded());
        if (!is_array($present) || !array_is_list($present)) {
            return WorkflowDifference::unwritableShape($place, self::shape($held), 'a list');
        }
        foreach ($items as [$item, $source]) {
            if (in_array($item, $present, true)) {
                continue;
            }

            return is_array($held->decoded())
                ? WorkflowDifference::missingListItem($path, $place, $source)
                : WorkflowDifference::unwritableShape($place, 'a single value', 'a list');
        }

        return null;
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
