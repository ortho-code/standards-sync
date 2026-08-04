<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel;

use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Formats\Xml\XmlElementWriter;
use AlleKnalle\StandardsSync\Rules\Psalm\PsalmConfigFile;

/**
 * Keeps the Psalm error level at or below a loosest allowed value — on psalm's inverted scale (1 strictest, 8 loosest) the numeric ceiling is the semantic strictness floor.
 * A looser written level is lowered to the limit, a stricter or equal one is never touched; an absent errorLevel attribute means psalm's default and is made explicit (capped at the limit); a missing config is created at the limit, so bootstrapped configs start at the org standard.
 */
final readonly class PsalmLoosestErrorLevel implements Rule, ExplainsDrift
{
    private const string ERROR_LEVEL = 'errorLevel';

    public function __construct(private PsalmErrorLevel $loosest)
    {
    }

    public function loosest(): PsalmErrorLevel
    {
        return $this->loosest;
    }

    public function target(): FileTarget
    {
        return PsalmConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        if ($content === null) {
            return PsalmConfigFile::createConfig($this->loosest);
        }

        // The writer leaves a config already carrying the enforced value byte-identical, so the compliant path needs no branch of its own.
        return XmlElementWriter::writeAttribute($content, PsalmConfigFile::ROOT_ELEMENT, self::ERROR_LEVEL, (string) $this->enforcedLevel($content)->value());
    }

    public function description(): string
    {
        return sprintf('Keeps the Psalm error level at or below %d (lower is stricter).', $this->loosest->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no Psalm config yet; one is created with error level %d.', $this->loosest->value());
        }

        $written = XmlElementWriter::readAttribute($content, PsalmConfigFile::ROOT_ELEMENT, self::ERROR_LEVEL);
        if ($written === null) {
            return sprintf('No errorLevel is written; the implicit default is made explicit as %d.', $this->enforcedLevel($content)->value());
        }

        $level = PsalmErrorLevel::fromConfigValue($written);
        if ($level->isAtMost($this->loosest)) {
            return sprintf('Level %d complies with the loosest allowed %d.', $level->value(), $this->loosest->value());
        }

        return sprintf('Level %d is looser than the loosest allowed %d.', $level->value(), $this->loosest->value());
    }

    // The level the config must carry: the written level — or psalm's implicit default when the attribute is absent — capped at the loosest allowed.
    private function enforcedLevel(string $content): PsalmErrorLevel
    {
        $written = XmlElementWriter::readAttribute($content, PsalmConfigFile::ROOT_ELEMENT, self::ERROR_LEVEL);
        $level = $written === null ? PsalmErrorLevel::createDefault() : PsalmErrorLevel::fromConfigValue($written);

        return $level->isAtMost($this->loosest) ? $level : $this->loosest;
    }
}
