<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Ecs\BaseSet;

use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Formats\Php\DirAnchoredEntry;
use AlleKnalle\StandardsSync\Formats\Php\FluentChainWriter;
use AlleKnalle\StandardsSync\Rules\Ecs\EcsConfigFile;

/**
 * Ensures the ECS config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched.
 * The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP.
 * The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent.
 * A config without withSets() gains the call at the end of the chain; a project without an ECS config gets one created, holding just the import.
 */
final readonly class EcsBaseSet implements Rule, ExplainsDrift
{
    private const string METHOD = 'withSets';

    private DirAnchoredEntry $entry;

    public function __construct(string $set)
    {
        $this->entry = DirAnchoredEntry::fromRelativeString($set);
    }

    public function target(): FileTarget
    {
        return EcsConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without an ECS config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        if ($content === null) {
            return EcsConfigFile::createConfig(FluentChainWriter::createArrayCall(self::METHOD, $this->entry->value()));
        }

        EcsConfigFile::assertFluentChain($content);

        return FluentChainWriter::ensureArrayEntry($content, self::METHOD, $this->entry->value());
    }

    public function description(): string
    {
        return sprintf('Ensures the ECS config registers %s in withSets().', $this->entry->path()->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no ECS config yet; one is created registering %s.', $this->entry->path()->value());
        }

        return sprintf('The ECS config does not register %s in withSets().', $this->entry->path()->value());
    }
}
