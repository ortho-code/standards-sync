<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\General\ListContribution;

use Closure;

/**
 * The entries a list contribution declares, each keyed by the text it is written into the list as, and the entries declared at the last sync and declared no longer.
 * Merging keeps each key's first declaration, in declaration order.
 *
 * @template TEntry
 */
final readonly class DeclaredEntries
{
    /**
     * @param non-empty-list<TEntry> $entries
     * @param Closure(TEntry): string $key
     * @param list<string> $retired
     */
    private function __construct(
        private array $entries,
        private Closure $key,
        private array $retired,
    ) {}

    /**
     * An entry that is its own key.
     *
     * @return self<string>
     */
    public static function fromString(string $entry): self
    {
        return new self([$entry], static fn(string $entry): string => $entry, []);
    }

    /**
     * @template TNew
     * @param TNew $entry
     * @param Closure(TNew): string $key the text the entry is written into the list as
     * @return self<TNew>
     */
    public static function fromEntry(mixed $entry, Closure $key): self
    {
        return new self([$entry], $key, []);
    }

    /**
     * These entries followed by a later declaration's, a key already declared keeping its first declaration.
     *
     * @param self<TEntry> $later
     * @return self<TEntry>
     */
    public function withMerged(self $later): self
    {
        $entries = $this->entries;
        foreach ($later->entries as $entry) {
            if (!in_array(($this->key)($entry), array_map($this->key, $entries), true)) {
                $entries[] = $entry;
            }
        }

        return new self($entries, $this->key, $this->retired);
    }

    /**
     * @param list<string> $retired the keys declared at the last sync and declared no longer
     * @return self<TEntry>
     */
    public function withRetired(array $retired): self
    {
        return new self($this->entries, $this->key, $retired);
    }

    /** @return non-empty-list<TEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return non-empty-list<string> */
    public function keys(): array
    {
        return array_map($this->key, $this->entries);
    }

    /** @return list<string> */
    public function retired(): array
    {
        return $this->retired;
    }

    /**
     * @param list<string> $present the keys the list holds
     * @return list<TEntry> the declared entries the list does not hold
     */
    public function missingFrom(array $present): array
    {
        return array_values(array_filter($this->entries, fn(mixed $entry): bool => !in_array(($this->key)($entry), $present, true)));
    }

    /**
     * @param list<string> $present the keys the list holds
     * @return list<string> the retired keys the list still holds
     */
    public function retractedFrom(array $present): array
    {
        return array_values(array_filter($this->retired, static fn(string $retired): bool => in_array($retired, $present, true)));
    }
}
