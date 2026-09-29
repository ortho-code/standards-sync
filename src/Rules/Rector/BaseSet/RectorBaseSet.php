<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Rector\BaseSet;

use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Php\DirAnchoredEntry;
use OrthoCode\StandardsSync\Formats\Php\FluentChainWriter;
use OrthoCode\StandardsSync\Rules\General\ListContribution\DeclaredEntries;
use OrthoCode\StandardsSync\Rules\Rector\RectorConfigFile;
use LogicException;

/**
 * Ensures the Rector config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched.
 * The set is a path relative to the consumer project root; the config entry (`__DIR__ . '/…'`) is rendered from it, so the org author passes data, not PHP.
 * The rendered entry is matched verbatim — no quote-stripping, because two spellings of one path are different expressions; a deviating hand-written spelling reads as absent.
 * A missing entry takes the place of one no standard declares any more, and goes after the last entry otherwise; a config without withSets() gains the call at the end of the chain; a project without a Rector config gets one created, holding just the imports.
 * Declarations of base sets combine in declaration order, a set declared twice counting once; a set registered at an earlier sync and declared by nobody now is retracted, and every other set is the project's and stays.
 */
final readonly class RectorBaseSet implements Rule, ContributesToList, ExplainsDrift
{
    private const string METHOD = 'withSets';

    /** @var DeclaredEntries<DirAnchoredEntry> */
    private DeclaredEntries $sets;

    public function __construct(string $set)
    {
        $this->sets = DeclaredEntries::fromEntries([DirAnchoredEntry::fromRelativeString($set)], static fn(DirAnchoredEntry $set): string => $set->value());
    }

    #[\Override]
    public function target(): FileTarget
    {
        return RectorConfigFile::target();
    }

    #[\Override]
    public function listKey(): string
    {
        return self::METHOD;
    }

    #[\Override]
    public function entries(): array
    {
        return $this->sets->keys();
    }

    #[\Override]
    public function withMerged(ContributesToList $later): static
    {
        if (!$later instanceof self) {
            throw new LogicException('Only declarations of Rector base sets merge into one.');
        }

        /** @var static $merged psalm types clone-with as a plain object */
        $merged = clone($this, [
            'sets' => $this->sets->withMerged($later->sets),
        ]);

        return $merged;
    }

    #[\Override]
    public function withRetired(array $retired): static
    {
        /** @var static $retiring psalm types clone-with as a plain object */
        $retiring = clone($this, [
            'sets' => $this->sets->withRetired($retired),
        ]);

        return $retiring;
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        // A project without a Rector config gets one: enforcing the standard is the point.
        $content ??= RectorConfigFile::createConfig(FluentChainWriter::createArrayCall(self::METHOD, $this->sets->keys()[0]));

        RectorConfigFile::assertFluentChain($content);
        foreach ($this->sets->keys() as $entry) {
            $content = FluentChainWriter::ensureArrayEntry($content, self::METHOD, $entry, replacing: $this->sets->retired());
        }

        return FluentChainWriter::removeArrayEntries($content, self::METHOD, $this->sets->retired());
    }

    #[\Override]
    public function description(): string
    {
        return sprintf('Ensures the Rector config registers %s in withSets().', self::paths($this->sets->entries()));
    }

    #[\Override]
    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no Rector config yet; one is created registering %s.', self::paths($this->sets->entries()));
        }

        $registered = FluentChainWriter::readArrayEntries($content, self::METHOD) ?? [];

        $sentences = [];
        $missing = $this->sets->missingFrom($registered);
        if ($missing !== []) {
            $sentences[] = sprintf('The Rector config does not register %s in withSets().', self::paths($missing));
        }

        $retracted = $this->sets->retractedFrom($registered);
        if ($retracted !== []) {
            $sentences[] = sprintf('It stops registering %s, which no standard declares any more.', implode(', ', $retracted));
        }

        return $sentences === [] ? 'The Rector config\'s withSets() differs from what the standards declare.' : implode(' ', $sentences);
    }

    /** @param list<DirAnchoredEntry> $sets */
    private static function paths(array $sets): string
    {
        return implode(', ', array_map(static fn(DirAnchoredEntry $set): string => $set->path()->value(), $sets));
    }
}
