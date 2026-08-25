<?php

declare(strict_types=1);

namespace StandardsSync\Documentation\Model;

/** One file's before/after demonstration within a scenario (a null side means the file is absent on that side). */
final readonly class FileExample
{
    public function __construct(
        private string $path,
        private ExampleKind $kind,
        private ?string $before,
        private ?string $after,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function kind(): ExampleKind
    {
        return $this->kind;
    }

    public function before(): ?string
    {
        return $this->before;
    }

    public function after(): ?string
    {
        return $this->after;
    }
}
