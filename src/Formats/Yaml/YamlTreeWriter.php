<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlLines;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValueKind;
use RuntimeException;

/**
 * Narrow edits to a YAML document at located positions: every line outside the edit keeps its bytes, its line ending included.
 * A path is a list of mapping keys and sequence indexes; an edit the document's shape does not allow is refused, naming the path.
 */
final readonly class YamlTreeWriter
{
    /** What separates a path's steps when a message spells it. */
    private const string PATH_SEPARATOR = '.';

    /**
     * A single-line scalar's text replaced by the given source, written as given; the comment after it kept or dropped.
     *
     * @param list<string|int> $path
     */
    public static function replaceScalar(string $content, array $path, string $source, bool $keepComment = true): string
    {
        $tree = YamlTree::fromString($content);
        $value = $tree->valueAt($path) ?? throw new RuntimeException(sprintf('"%s" leads to no value; it cannot be replaced.', self::spelled($path)));
        if (!in_array($value->kind(), [YamlValueKind::Plain, YamlValueKind::Quoted], true) || $value->isMultiline()) {
            throw new RuntimeException(sprintf('"%s" does not hold a single-line scalar; it cannot be replaced in place.', self::spelled($path)));
        }

        $line = $tree->lines()->line($value->line());
        $rest = $keepComment ? substr($line, $value->endColumn()) : '';

        return $tree->lines()->withLine($value->line(), substr($line, 0, $value->startColumn()) . $source . $rest)->toString();
    }

    /**
     * An entry's value replaced by the fragment's, the key's line kept up to its colon.
     *
     * @param non-empty-list<string|int> $path the entry's path, ending in its key
     */
    public static function replaceValue(string $content, array $path, YamlFragment $value): string
    {
        $tree = YamlTree::fromString($content);
        $entry = self::entryAt($tree, $path);
        [$inline, $following] = $value->valueLines(YamlStyle::fromTree($tree), $entry->column());
        $keyLine = substr($tree->lines()->line($entry->line()), 0, $entry->keyEnd());

        return $tree->lines()
            ->withLine($entry->line(), $inline === '' ? $keyLine : $keyLine . ' ' . $inline)
            ->withRemoved($entry->line() + 1, $entry->end())
            ->withInserted($entry->line() + 1, $following)
            ->toString();
    }

    /**
     * The fragment's entry added after the mapping's last entry; a key holding no value gains it as a nested mapping.
     *
     * @param list<string|int> $mappingPath
     */
    public static function addEntry(string $content, array $mappingPath, YamlFragment $entry): string
    {
        $tree = YamlTree::fromString($content);
        $style = YamlStyle::fromTree($tree);
        if ($mappingPath === []) {
            return self::inserted($tree->lines(), $tree->root()->end(), $entry->entryLines($style, $tree->root()->indent()));
        }

        $value = $tree->valueAt($mappingPath) ?? throw new RuntimeException(sprintf('"%s" leads to no value; an entry cannot be added to it.', self::spelled($mappingPath)));
        $node = $value->node();
        if ($node instanceof YamlMapping) {
            return self::inserted($tree->lines(), $node->end(), $entry->entryLines($style, $node->indent()));
        }
        $key = end($mappingPath);
        if ($value->kind() === YamlValueKind::Empty && is_string($key)) {
            $owner = self::entryAt($tree, $mappingPath);

            return self::inserted($tree->lines(), $owner->line() + 1, $entry->entryLines($style, $owner->column() + $style->unit()));
        }

        throw new RuntimeException(sprintf('"%s" holds neither a block mapping nor nothing; an entry cannot be added to it.', self::spelled($mappingPath)));
    }

    /**
     * The fragment's item inserted before the item at the index, or after the last; its dash stands where the sequence's do.
     * An item goes in before the comment lines leading the item it is inserted before.
     *
     * @param list<string|int> $sequencePath
     */
    public static function insertItem(string $content, array $sequencePath, int $index, YamlFragment $item): string
    {
        $tree = YamlTree::fromString($content);
        $sequence = self::sequenceAt($tree, $sequencePath);
        $items = $sequence->items();
        $at = match (true) {
            $index <= 0 => $sequence->leadStart(),
            $index >= count($items) => $items[count($items) - 1]->end(),
            default => $items[$index - 1]->end(),
        };
        $style = YamlStyle::fromTree($tree);

        $ownOffset = array_find(array_map(static fn(YamlItem $sibling): ?int => $sibling->contentOffset(), $items), static fn(?int $offset): bool => $offset !== null);

        return self::inserted($tree->lines(), $at, $item->itemLines($style, $sequence->dashColumn(), $ownOffset ?? $style->itemOffset()));
    }

    /**
     * A scalar appended to a block sequence or to a flow sequence on one line.
     *
     * @param list<string|int> $sequencePath
     */
    public static function appendScalarItem(string $content, array $sequencePath, string $source): string
    {
        $tree = YamlTree::fromString($content);
        $value = $tree->valueAt($sequencePath) ?? throw new RuntimeException(sprintf('"%s" leads to no value; an item cannot be appended to it.', self::spelled($sequencePath)));
        $node = $value->node();
        if ($node instanceof YamlSequence) {
            return self::insertItem($content, $sequencePath, count($node->items()), YamlFragment::fromScalarItem($source));
        }
        if ($value->kind() === YamlValueKind::Flow && !$value->isMultiline() && str_starts_with($value->source(), '[')) {
            $inner = substr($value->source(), 1, -1);
            $body = rtrim($inner);
            $appended = match (true) {
                $body === '' => '[' . $source . ']',
                str_ends_with($body, ',') => '[' . $body . ' ' . $source . substr($inner, strlen($body)) . ']',
                default => '[' . $body . ', ' . $source . substr($inner, strlen($body)) . ']',
            };
            $line = $tree->lines()->line($value->line());

            return $tree->lines()->withLine($value->line(), substr($line, 0, $value->startColumn()) . $appended . substr($line, $value->endColumn()))->toString();
        }

        throw new RuntimeException(sprintf('"%s" holds neither a block sequence nor a flow sequence on one line; an item cannot be appended to it.', self::spelled($sequencePath)));
    }

    /**
     * The item at the index removed, with the comment lines directly above it.
     *
     * @param list<string|int> $sequencePath
     */
    public static function removeItem(string $content, array $sequencePath, int $index): string
    {
        $tree = YamlTree::fromString($content);
        $sequence = self::sequenceAt($tree, $sequencePath);
        $items = $sequence->items();
        $item = $items[$index] ?? throw new RuntimeException(sprintf('"%s" holds no item %d; it cannot be removed.', self::spelled($sequencePath), $index));
        if (count($items) === 1) {
            throw new RuntimeException(sprintf('"%s" holds only this item; removing it would leave no sequence, so the entry holding it goes instead.', self::spelled($sequencePath)));
        }
        $floor = $index === 0 ? $sequence->leadStart() : $items[$index - 1]->end();

        return $tree->lines()->withRemoved(self::commentsAbove($tree->lines(), $item->line(), $floor), $item->end())->toString();
    }

    /**
     * The entry removed, with the comment lines directly above it; removing the key an item opens with moves the dash to the item's next key.
     *
     * @param list<string|int> $mappingPath
     */
    public static function removeEntry(string $content, array $mappingPath, string $key): string
    {
        $tree = YamlTree::fromString($content);
        $mapping = $mappingPath === [] ? $tree->root() : $tree->valueAt($mappingPath)?->node();
        if (!$mapping instanceof YamlMapping) {
            throw new RuntimeException(sprintf('"%s" does not hold a block mapping; an entry cannot be removed from it.', self::spelled($mappingPath)));
        }
        $entries = $mapping->entries();
        /** @var int|null $position a list's keys are its positions */
        $position = array_find_key($entries, static fn(YamlEntry $entry): bool => $entry->key() === $key);
        if ($position === null) {
            throw new RuntimeException(sprintf('"%s" holds no entry "%s"; it cannot be removed.', self::spelled($mappingPath), $key));
        }
        if (count($entries) === 1) {
            throw new RuntimeException(sprintf('"%s" holds only the entry "%s"; removing it would leave no mapping, so the entry holding it goes instead.', self::spelled($mappingPath), $key));
        }

        $lines = $tree->lines();
        $entry = $entries[$position];
        if ($entry->opensItem()) {
            $next = $entries[1];
            $dashPrefix = substr($lines->line($entry->line()), 0, $entry->column());

            return $lines
                ->withLine($next->line(), $dashPrefix . substr($lines->line($next->line()), $entry->column()))
                ->withRemoved($entry->line(), $entry->end())
                ->toString();
        }
        $floor = $position === 0 ? 0 : $entries[$position - 1]->end();

        return $lines->withRemoved(self::commentsAbove($lines, $entry->line(), $floor), $entry->end())->toString();
    }

    /** @param list<string> $texts */
    private static function inserted(YamlLines $lines, int $at, array $texts): string
    {
        return $lines->withInserted($at, $texts)->toString();
    }

    /** @param non-empty-list<string|int> $path */
    private static function entryAt(YamlTree $tree, array $path): YamlEntry
    {
        $key = $path[count($path) - 1];
        $parentPath = array_slice($path, 0, -1);
        $parent = $parentPath === [] ? $tree->root() : $tree->valueAt($parentPath)?->node();
        $entry = is_string($key) && $parent instanceof YamlMapping ? $parent->entry($key) : null;

        return $entry ?? throw new RuntimeException(sprintf('"%s" leads to no entry.', self::spelled($path)));
    }

    /** @param list<string|int> $path */
    private static function sequenceAt(YamlTree $tree, array $path): YamlSequence
    {
        $node = $tree->valueAt($path)?->node();

        return $node instanceof YamlSequence ? $node : throw new RuntimeException(sprintf('"%s" does not hold a block sequence.', self::spelled($path)));
    }

    /** The first line of the comment block directly above a line, no further up than the floor and not across a blank line. */
    private static function commentsAbove(YamlLines $lines, int $line, int $floor): int
    {
        $first = $line;
        while ($first - 1 >= $floor && str_starts_with(ltrim($lines->line($first - 1)), '#')) {
            $first--;
        }

        return $first;
    }

    /** @param list<string|int> $path */
    private static function spelled(array $path): string
    {
        return implode(self::PATH_SEPARATOR, $path);
    }
}
