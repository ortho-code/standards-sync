<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Ecs\BaseSet;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\Text\Lines;
use AlleKnalle\StandardsSync\Formats\Php\FluentChainWriter;
use AlleKnalle\StandardsSync\Rules\Ecs\EcsConfigFile;
use InvalidArgumentException;

/**
 * Ensures the ECS config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched.
 * The set is a path relative to the consumer project root; the rule renders the config entry (`__DIR__ . '/…'`) itself, so the org author passes data, not PHP.
 * The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent.
 * A config without withSets() gains the call at the end of the chain; a project without an ECS config gets one created, holding just the import.
 */
final readonly class EcsBaseSet implements Rule, ExplainsDrift
{
    private const string METHOD = 'withSets';

    private Path $set;

    public function __construct(string $set)
    {
        if (str_contains($set, '__DIR__') || str_contains($set, "'") || str_contains($set, '"')) {
            throw new InvalidArgumentException("Pass the set file's relative path; the rule renders the PHP expression.");
        }

        $this->set = Path::fromString($set);
        if ($this->set->isAbsolute()) {
            throw new InvalidArgumentException('The set path must be relative to the project root.');
        }
    }

    public function target(): FileTarget
    {
        return EcsConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without an ECS config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        if ($content === null) {
            return EcsConfigFile::createConfig($this->setsCall());
        }

        EcsConfigFile::assertFluentChain($content);

        return FluentChainWriter::ensureArrayEntry($content, self::METHOD, $this->entry());
    }

    public function description(): string
    {
        return sprintf('Ensures the ECS config registers %s in withSets().', $this->set->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no ECS config yet; one is created registering %s.', $this->set->value());
        }

        return sprintf('The ECS config does not register %s in withSets().', $this->set->value());
    }

    /** The canonical config entry for the set: resolved by ECS in the consumer project, where __DIR__ is the project root. */
    private function entry(): string
    {
        return "__DIR__ . '/" . $this->set->value() . "'";
    }

    private function setsCall(): string
    {
        $indent = FluentChainWriter::INDENT;

        return '->' . self::METHOD . '([' . Lines::LINE_BREAK
            . $indent . $indent . $this->entry() . ',' . Lines::LINE_BREAK
            . $indent . '])';
    }
}
