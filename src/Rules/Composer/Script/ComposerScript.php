<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Composer\Script;

use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Json\JsonObjectWriter;
use OrthoCode\StandardsSync\Rules\Composer\ComposerManifest;
use InvalidArgumentException;

/**
 * Owns a named composer script: the declared commands are what the script runs, and a deviating or missing script is rewritten.
 * A project needing extra steps declares a script of its own and calls this one through composer's "@name" reference, so the owned entry point stays exactly what the standard says.
 * A root without a manifest is not a composer project, so the rule abstains rather than creating one.
 */
final readonly class ComposerScript implements Rule
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
