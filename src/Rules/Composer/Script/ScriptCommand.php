<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Composer\Script;

/** One declared command of a composer script, and whether it accepts extra arguments after it. */
final readonly class ScriptCommand
{
    /** What separates a command from the arguments after it; without it, "analyse" would match "analyse-nothing". */
    private const string ARGUMENT_SEPARATOR = ' ';

    private function __construct(
        private string $command,
        private bool $acceptsArguments,
    ) {}

    public static function fromString(string $command, bool $acceptsArguments): self
    {
        return new self($command, $acceptsArguments);
    }

    public function value(): string
    {
        return $this->command;
    }

    public function acceptsArguments(): bool
    {
        return $this->acceptsArguments;
    }

    /** True when the actual command is this one, or — accepting arguments — this one followed by a space and more. */
    public function matches(string $actual): bool
    {
        return $actual === $this->command
            || ($this->acceptsArguments && str_starts_with($actual, $this->command . self::ARGUMENT_SEPARATOR));
    }
}
