<?php

declare(strict_types=1);

namespace StandardsSync\Formats\Json;

/** One JSON object as it sits in the document: the span from its opening to its closing brace, and its members in document order. */
final readonly class JsonObject
{
    /** @param array<string, JsonMember> $members keyed by decoded name */
    public function __construct(
        private int $start,
        private int $end,
        private array $members,
    ) {
    }

    /** The offset of the opening brace. */
    public function start(): int
    {
        return $this->start;
    }

    /** The offset of the closing brace. */
    public function end(): int
    {
        return $this->end;
    }

    /** @return array<string, JsonMember> */
    public function members(): array
    {
        return $this->members;
    }

    public function member(string $key): ?JsonMember
    {
        return $this->members[$key] ?? null;
    }

    public function last(): ?JsonMember
    {
        return $this->members === [] ? null : $this->members[array_key_last($this->members)];
    }
}
