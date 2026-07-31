<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Rector\BaseSet;

use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\Text\Lines;
use AlleKnalle\StandardsSync\Formats\Php\FluentChainWriter;
use AlleKnalle\StandardsSync\Rules\Rector\RectorConfigFile;
use InvalidArgumentException;

/**
 * Ensures the Rector config registers a given set file in withSets(), as a targeted edit that leaves the rest of the config untouched.
 * The entry is PHP expression text (e.g. `__DIR__ . '/vendor/…'`), matched verbatim — unlike the neon import there is no quote-stripping, because two spellings of one path are different expressions.
 * A config without withSets() gains the call at the end of the chain; a project without a Rector config gets one created, holding just the import.
 */
final readonly class RectorBaseSet implements Rule, ExplainsDrift
{
    private const string METHOD = 'withSets';

    public function __construct(private string $set)
    {
        if (trim($this->set) === '') {
            throw new InvalidArgumentException('The set entry cannot be empty.');
        }
    }

    public function target(): FileTarget
    {
        return RectorConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without a Rector config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        if ($content === null) {
            return RectorConfigFile::createConfig($this->setsCall());
        }

        RectorConfigFile::assertFluentChain($content);

        return FluentChainWriter::ensureArrayEntry($content, self::METHOD, $this->set);
    }

    public function description(): string
    {
        return sprintf('Ensures the Rector config registers %s in withSets().', $this->set);
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no Rector config yet; one is created registering %s.', $this->set);
        }

        return sprintf('The Rector config does not register %s in withSets().', $this->set);
    }

    private function setsCall(): string
    {
        $indent = FluentChainWriter::INDENT;

        return '->' . self::METHOD . '([' . Lines::LINE_BREAK
            . $indent . $indent . $this->set . ',' . Lines::LINE_BREAK
            . $indent . '])';
    }
}
