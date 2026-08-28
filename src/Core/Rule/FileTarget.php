<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Rule;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use InvalidArgumentException;

/**
 * The file a rule targets, as ordered path candidates (the tool's own precedence); which candidate stands for the target is resolved per root at plan time.
 * Candidates must be relative, because one rule fans across every configured root.
 */
final readonly class FileTarget
{
    /** @param non-empty-list<Path> $candidates */
    private function __construct(private array $candidates) {}

    public static function fromString(string $path): self
    {
        return self::fromStrings($path);
    }

    public static function fromStrings(string ...$candidates): self
    {
        $paths = [];
        foreach ($candidates as $candidate) {
            $paths[] = Path::fromRelativeString($candidate);
        }

        if ($paths === []) {
            throw new InvalidArgumentException('A file target needs at least one candidate path.');
        }

        return new self($paths);
    }

    /** @return non-empty-list<Path> */
    public function candidates(): array
    {
        return $this->candidates;
    }

    /** The candidate list as one display string, in precedence order. */
    public function toString(): string
    {
        return implode(' | ', array_map(static fn(Path $path): string => $path->value(), $this->candidates));
    }
}
