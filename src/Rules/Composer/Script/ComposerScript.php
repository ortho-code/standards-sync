<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Composer\Script;

use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Rules\Composer\ComposerManifest;
use InvalidArgumentException;
use LogicException;

/**
 * Declares commands a named composer script runs: each is enforced present, a missing one inserted after the command declared before it, and every other command in the script is the project's and stays.
 * Declarations of one script combine in declaration order, each adding its commands after the earlier ones'; a command declared twice counts once.
 * A command declared at an earlier sync and declared by nobody now is retracted, with or without arguments after it.
 * A root without a manifest is not a composer project, so the rule abstains rather than creating one.
 */
final readonly class ComposerScript implements Rule, ContributesToList, ExplainsDrift
{
    /** @var non-empty-list<ScriptCommand> */
    private array $commands;

    /** @var list<string> */
    private array $retired;

    /**
     * The commands are validated rather than only typed: an org package is plain PHP, so the docblock is a promise the caller can break.
     *
     * @param list<string> $commands
     * @param bool $acceptsArguments whether a project may add arguments after each command and still run it
     */
    public function __construct(
        private string $name,
        array $commands,
        bool $acceptsArguments = false,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A composer script needs a name.');
        }
        if ($commands === []) {
            throw new InvalidArgumentException(sprintf('The composer script "%s" needs at least one command.', $name));
        }

        $this->commands = array_map(static fn(string $command): ScriptCommand => ScriptCommand::fromString($command, $acceptsArguments), $commands);
        $this->retired = [];
    }

    /** Exposed so an org can pin whatever calls this script by name against what it declares. */
    public function name(): string
    {
        return $this->name;
    }

    #[\Override]
    public function target(): FileTarget
    {
        return ComposerManifest::target();
    }

    #[\Override]
    public function listKey(): string
    {
        return ComposerManifest::SCRIPTS_SECTION . '.' . $this->name;
    }

    #[\Override]
    public function entries(): array
    {
        return array_map(static fn(ScriptCommand $command): string => $command->value(), $this->commands);
    }

    #[\Override]
    public function withMerged(ContributesToList $later): static
    {
        if (!$later instanceof self || $later->name !== $this->name) {
            throw new LogicException(sprintf('Only declarations of the composer script "%s" merge into it.', $this->name));
        }

        $commands = $this->commands;
        foreach ($later->commands as $command) {
            if (!array_any($commands, static fn(ScriptCommand $declared): bool => $declared->value() === $command->value())) {
                $commands[] = $command;
            }
        }

        /** @var static $merged psalm types clone-with as a plain object */
        $merged = clone($this, [
            'commands' => $commands,
        ]);

        return $merged;
    }

    #[\Override]
    public function withRetired(array $retired): static
    {
        /** @var static $retiring psalm types clone-with as a plain object */
        $retiring = clone($this, [
            'retired' => $retired,
        ]);

        return $retiring;
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $path = $this->path();
        $current = JsonObjectWriter::readList($content, $path);
        $desired = $this->commandsFor($current ?? []);

        return $desired === $current ? $content : JsonObjectWriter::writeList($content, $path, $desired);
    }

    #[\Override]
    public function explain(?string $content): string
    {
        $current = $content === null ? [] : JsonObjectWriter::readList($content, $this->path()) ?? [];

        $sentences = [];
        $missing = array_values(array_filter(
            $this->commands,
            static fn(ScriptCommand $declared): bool => !array_any($current, static fn(string $actual): bool => $declared->matches($actual)),
        ));
        if ($missing !== []) {
            $sentences[] = sprintf('It does not run %s yet.', self::quoted(self::values($missing)));
        }

        $retracted = array_values(array_filter($current, fn(string $actual): bool => !$this->declares($actual) && $this->retires($actual)));
        if ($retracted !== []) {
            $sentences[] = sprintf('It stops running %s, which no standard declares any more.', self::quoted($retracted));
        }

        $kept = array_values(array_filter($current, fn(string $actual): bool => !$this->declares($actual) && !$this->retires($actual)));
        if ($kept !== []) {
            $sentences[] = sprintf('The project\'s own %s %s.', self::quoted($kept), count($kept) === 1 ? 'stays' : 'stay');
        }

        return $sentences === [] ? sprintf('The composer script "%s" differs from what the standards declare.', $this->name) : implode(' ', $sentences);
    }

    #[\Override]
    public function description(): string
    {
        $description = sprintf('Runs %s in the composer script "%s", beside any commands the project adds.', self::quoted(self::values($this->commands)), $this->name);

        $accepting = array_values(array_filter($this->commands, static fn(ScriptCommand $command): bool => $command->acceptsArguments()));
        if ($accepting !== []) {
            $description .= sprintf(' %s %s extra arguments.', self::quoted(self::values($accepting)), count($accepting) === 1 ? 'accepts' : 'accept');
        }

        return $description;
    }

    /** @return non-empty-list<string> */
    private function path(): array
    {
        return [ComposerManifest::SCRIPTS_SECTION, $this->name];
    }

    /**
     * The project's commands with the retired ones taken out, and every declared command that is missing inserted after the one declared before it.
     *
     * @param list<string> $current
     * @return non-empty-list<string>
     */
    private function commandsFor(array $current): array
    {
        $commands = array_values(array_filter($current, fn(string $actual): bool => $this->declares($actual) || !$this->retires($actual)));

        $cursor = 0;
        foreach ($this->commands as $declared) {
            /** @var int|null $position a list's keys are its positions */
            $position = array_find_key($commands, static fn(string $actual): bool => $declared->matches($actual));
            if ($position === null) {
                array_splice($commands, $cursor, 0, [$declared->value()]);
                $position = $cursor;
            }
            $cursor = $position + 1;
        }

        /** @var non-empty-list<string> $commands every declared command is present by now */
        return $commands;
    }

    private function declares(string $actual): bool
    {
        return array_any($this->commands, static fn(ScriptCommand $declared): bool => $declared->matches($actual));
    }

    /** A retired command is recognised with arguments after it too: the project's arguments do not make a command the standard stopped declaring the project's own. */
    private function retires(string $actual): bool
    {
        return array_any($this->retired, static fn(string $retired): bool => ScriptCommand::fromString($retired, acceptsArguments: true)->matches($actual));
    }

    /**
     * @param list<ScriptCommand> $commands
     * @return list<string>
     */
    private static function values(array $commands): array
    {
        return array_map(static fn(ScriptCommand $command): string => $command->value(), $commands);
    }

    /** @param list<string> $commands */
    private static function quoted(array $commands): string
    {
        return '"' . implode('", "', $commands) . '"';
    }
}
