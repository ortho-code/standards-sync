<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/** A block mapping: its entries in order, their keys in one column. */
final readonly class YamlMapping
{
    /** @param non-empty-list<YamlEntry> $entries */
    public function __construct(
        private int $indent,
        private array $entries,
        private int $end,
    ) {}

    /** The column every key of the mapping stands in. */
    public function indent(): int
    {
        return $this->indent;
    }

    /** @return non-empty-list<YamlEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function entry(string $key): ?YamlEntry
    {
        return array_find($this->entries, static fn(YamlEntry $entry): bool => $entry->key() === $key);
    }

    /** The first line after the mapping's last entry. */
    public function end(): int
    {
        return $this->end;
    }

    /** @return array<array-key, mixed> */
    public function decoded(): array
    {
        $decoded = [];
        foreach ($this->entries as $entry) {
            $decoded[$entry->key()] = $entry->value()->decoded();
        }

        return $decoded;
    }
}
