<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Filesystem;

use InvalidArgumentException;

/** A filesystem location, relative or absolute, with safe joining. */
final readonly class Path
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('A path cannot be empty.');
        }

        return new self($value);
    }

    /** For the many places a path is only meaningful relative to some root: refuses an absolute value at construction. */
    public static function fromRelativeString(string $value): self
    {
        $path = self::fromString($value);
        if ($path->isAbsolute()) {
            throw new InvalidArgumentException(sprintf('A path must be relative; got "%s".', $value));
        }

        return $path;
    }

    public function isAbsolute(): bool
    {
        return str_starts_with($this->value, '/');
    }

    /** Appends a relative segment, collapsing the slash between the two parts. */
    public function join(self $segment): self
    {
        return new self(rtrim($this->value, '/') . '/' . ltrim($segment->value, '/'));
    }

    public function value(): string
    {
        return $this->value;
    }
}
