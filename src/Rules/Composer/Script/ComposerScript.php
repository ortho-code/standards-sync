<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Composer\Script;

use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Rules\Composer\ComposerManifest;
use InvalidArgumentException;
use LogicException;

/**
 * Owns a named composer script: the declared commands are what the script runs, and a deviating or missing script is rewritten.
 * Declarations of one script combine in declaration order, each adding its commands after the earlier ones'; a command declared twice counts once.
 * A project needing extra steps declares a script of its own and calls this one through composer's "@name" reference, so the owned entry point stays exactly what the standards say.
 * A root without a manifest is not a composer project, so the rule abstains rather than creating one.
 */
final readonly class ComposerScript implements Rule, ContributesToList
{
    /** @var non-empty-list<string> */
    private array $commands;

    /**
     * The commands are validated rather than only typed: an org package is plain PHP, so the docblock is a promise the caller can break.
     *
     * @param list<string> $commands
     */
    public function __construct(
        private string $name,
        array $commands,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A composer script needs a name.');
        }
        if ($commands === []) {
            throw new InvalidArgumentException(sprintf('The composer script "%s" needs at least one command.', $name));
        }

        $this->commands = $commands;
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
    public function withMerged(ContributesToList $later): static
    {
        if (!$later instanceof self || $later->name !== $this->name) {
            throw new LogicException(sprintf('Only declarations of the composer script "%s" merge into it.', $this->name));
        }

        $commands = $this->commands;
        foreach ($later->commands as $command) {
            if (!in_array($command, $commands, true)) {
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
    public function apply(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return JsonObjectWriter::writeList($content, [ComposerManifest::SCRIPTS_SECTION, $this->name], $this->commands);
    }

    #[\Override]
    public function description(): string
    {
        return sprintf('Runs "%s" as the composer script "%s".', implode('", "', $this->commands), $this->name);
    }
}
