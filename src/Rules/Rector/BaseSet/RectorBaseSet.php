<?php

declare(strict_types=1);

namespace StandardsSync\Rules\Rector\BaseSet;

use StandardsSync\Core\Rule\ExplainsDrift;
use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\Rule\Rule;
use StandardsSync\Formats\Php\DirAnchoredEntry;
use StandardsSync\Formats\Php\FluentChainWriter;
use StandardsSync\Rules\Rector\RectorConfigFile;

/**
 * Ensures the Rector config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched.
 * The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP.
 * The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent.
 * A config without withSets() gains the call at the end of the chain; a project without a Rector config gets one created, holding just the import.
 */
final readonly class RectorBaseSet implements Rule, ExplainsDrift
{
    private const string METHOD = 'withSets';

    private DirAnchoredEntry $entry;

    public function __construct(string $set)
    {
        $this->entry = DirAnchoredEntry::fromRelativeString($set);
    }

    public function target(): FileTarget
    {
        return RectorConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without a Rector config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        if ($content === null) {
            return RectorConfigFile::createConfig(FluentChainWriter::createArrayCall(self::METHOD, $this->entry->value()));
        }

        RectorConfigFile::assertFluentChain($content);

        return FluentChainWriter::ensureArrayEntry($content, self::METHOD, $this->entry->value());
    }

    public function description(): string
    {
        return sprintf('Ensures the Rector config registers %s in withSets().', $this->entry->path()->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no Rector config yet; one is created registering %s.', $this->entry->path()->value());
        }

        return sprintf('The Rector config does not register %s in withSets().', $this->entry->path()->value());
    }
}
