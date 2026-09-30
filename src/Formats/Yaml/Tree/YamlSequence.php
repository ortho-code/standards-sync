<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/** A block sequence: its items in order, their dashes in one column. */
final readonly class YamlSequence
{
    /**
     * @param non-empty-list<YamlItem> $items
     * @param int $leadStart the first line after the key the sequence belongs to; comment lines from there to the first item lead that item
     */
    public function __construct(
        private int $dashColumn,
        private array $items,
        private int $leadStart,
        private int $end,
    ) {}

    public function dashColumn(): int
    {
        return $this->dashColumn;
    }

    /** @return non-empty-list<YamlItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function leadStart(): int
    {
        return $this->leadStart;
    }

    /** The first line after the sequence's last item. */
    public function end(): int
    {
        return $this->end;
    }

    /** @return list<mixed> */
    public function decoded(): array
    {
        return array_map(static fn(YamlItem $item): mixed => $item->value()->decoded(), $this->items);
    }
}
