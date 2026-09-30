<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

/**
 * The place of one declared node in a workflow, as a JSON Pointer (RFC 6901): mapping keys by name, a job's steps by id, a list's scalar items by value.
 * `/jobs/checks/steps/setup-php` is the step `setup-php`; `/on/push/branches/release~1**` is the pattern `release/**`.
 */
final readonly class WorkflowPointer
{
    private const string SEPARATOR = '/';

    private const array ESCAPED = ['~0', '~1'];

    private const array UNESCAPED = ['~', '/'];

    /** @param non-empty-list<string> $segments */
    private function __construct(
        private array $segments,
    ) {}

    /** @param non-empty-list<string> $segments */
    public static function fromSegments(array $segments): self
    {
        return new self($segments);
    }

    /** Null for text that is not a pointer to a node. */
    public static function fromString(string $pointer): ?self
    {
        if (!str_starts_with($pointer, self::SEPARATOR) || $pointer === self::SEPARATOR) {
            return null;
        }
        $segments = array_map(
            static fn(string $segment): string => str_replace(self::ESCAPED, self::UNESCAPED, $segment),
            explode(self::SEPARATOR, substr($pointer, 1)),
        );

        return new self($segments);
    }

    /** @return non-empty-list<string> */
    public function segments(): array
    {
        return $this->segments;
    }

    /** How many nodes deep it points, one for a top-level key. */
    public function depth(): int
    {
        return count($this->segments);
    }

    /** The pointer to the node holding this one, or null for a top-level key. */
    public function parent(): ?self
    {
        $segments = array_slice($this->segments, 0, -1);

        return $segments === [] ? null : new self($segments);
    }

    /** How a list's scalar item is written as a segment: as its text, a boolean as `true` or `false`. */
    public static function segmentOf(mixed $item): string
    {
        return match (true) {
            is_bool($item) => $item ? 'true' : 'false',
            is_scalar($item) => (string) $item,
            default => '',
        };
    }

    /** The pointer one segment further, to a child of this node. */
    public function to(string $segment): self
    {
        return new self([...$this->segments, $segment]);
    }

    public function toString(): string
    {
        return self::SEPARATOR . implode(self::SEPARATOR, array_map(
            static fn(string $segment): string => str_replace(self::UNESCAPED, self::ESCAPED, $segment),
            $this->segments,
        ));
    }
}
