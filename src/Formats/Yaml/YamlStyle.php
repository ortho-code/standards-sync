<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml;

use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;

/**
 * How a document indents, read from the document itself: the unit a nested mapping steps in by, where a sequence's dashes stand against its key, and where an item's content starts after its dash.
 * Each is the one the document uses most; a document showing none gets the conventional one.
 */
final readonly class YamlStyle
{
    /** The content of an item written `- value`: one space after the dash. */
    private const int CONVENTIONAL_ITEM_OFFSET = 2;

    private function __construct(
        private int $unit,
        private int $sequenceOffset,
        private int $itemOffset,
    ) {}

    public static function fromTree(YamlTree $tree): self
    {
        $units = [];
        $sequenceOffsets = [];
        $itemOffsets = [];
        $walk = static function (YamlMapping $mapping) use (&$walk, &$units, &$sequenceOffsets, &$itemOffsets): void {
            foreach ($mapping->entries() as $entry) {
                $node = $entry->value()->node();
                if ($node instanceof YamlMapping) {
                    $units[] = $node->indent() - $entry->column();
                    $walk($node);
                }
                if ($node instanceof YamlSequence) {
                    $sequenceOffsets[] = $node->dashColumn() - $entry->column();
                    foreach ($node->items() as $item) {
                        $offset = $item->contentOffset();
                        if ($offset !== null) {
                            $itemOffsets[] = $offset;
                        }
                        $content = $item->value()->node();
                        if ($content instanceof YamlMapping) {
                            $walk($content);
                        }
                    }
                }
            }
        };
        $walk($tree->root());

        $unit = self::mostUsed(array_values(array_filter($units, static fn(int $unit): bool => $unit > 0))) ?? strlen(YamlIndent::DEFAULT);

        return new self($unit, self::mostUsed($sequenceOffsets) ?? $unit, self::mostUsed($itemOffsets) ?? self::CONVENTIONAL_ITEM_OFFSET);
    }

    /** The columns a nested mapping's keys stand to the right of its key. */
    public function unit(): int
    {
        return $this->unit;
    }

    /** The columns a sequence's dashes stand to the right of its key; zero when they stand under it. */
    public function sequenceOffset(): int
    {
        return $this->sequenceOffset;
    }

    /** The columns an item's content starts to the right of its dash. */
    public function itemOffset(): int
    {
        return $this->itemOffset;
    }

    /** @param list<int> $values */
    private static function mostUsed(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        $counts = array_count_values($values);
        arsort($counts);

        return array_key_first($counts);
    }
}
