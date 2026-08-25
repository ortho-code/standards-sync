<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpStan\PinnedValues;

/** One pinned scalar: where it lives in the config (as a key path from the document root) and what it must be. */
final readonly class PinnedValue
{
    /** @param non-empty-list<string> $path */
    public function __construct(
        private array $path,
        private bool|int|string $value,
    ) {
    }

    /** @return non-empty-list<string> */
    public function path(): array
    {
        return $this->path;
    }

    public function value(): bool|int|string
    {
        return $this->value;
    }

    public function dottedPath(): string
    {
        return implode('.', $this->path);
    }
}
