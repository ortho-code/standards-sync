<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\PhpUnit\PinnedAttributes;

use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Xml\XmlElementWriter;
use OrthoCode\StandardsSync\Rules\PhpUnit\PhpUnitConfigFile;
use InvalidArgumentException;

/**
 * Pins exact root-attribute values in the PHPUnit config: deviations are rewritten on every sync, so consumers cannot override them.
 * Values a project may override belong in the seeded template instead — pin only what must not be overridden.
 * Comparison is text-exact: phpunit reads only the spellings "true" and "false" (case-insensitively) and silently treats every other spelling as false — "1" included, which the schema accepts — so any other spelling of a pinned value is drift and normalizes.
 * A pin always writes: a project without a PHPUnit config gets one created holding the pinned values.
 */
final readonly class PhpUnitPinnedAttributes implements Rule, ExplainsDrift
{
    private const array BOOLEAN_SPELLINGS = [PinnedAttribute::SPELLING_TRUE, PinnedAttribute::SPELLING_FALSE];

    /** @var non-empty-list<PinnedAttribute> */
    private array $attributes;

    /** @param array<string, bool|int|string> $attributes attribute name => the exact value it must carry */
    public function __construct(array $attributes)
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('At least one attribute must be pinned.');
        }

        $this->attributes = array_map(PinnedAttribute::fromNameAndValue(...), array_keys($attributes), $attributes);
    }

    /** @return non-empty-list<PinnedAttribute> */
    public function pinnedAttributes(): array
    {
        return $this->attributes;
    }

    #[\Override]
    public function target(): FileTarget
    {
        return PhpUnitConfigFile::target();
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        // The writer leaves an attribute already carrying its pinned value byte-identical, so the compliant path needs no branch of its own.
        $result = $content ?? PhpUnitConfigFile::createConfig();
        foreach ($this->attributes as $attribute) {
            $result = XmlElementWriter::writeAttribute($result, PhpUnitConfigFile::ROOT_ELEMENT, $attribute->name(), $attribute->value());
        }

        return $result;
    }

    #[\Override]
    public function description(): string
    {
        $names = array_map(static fn(PinnedAttribute $attribute): string => $attribute->name(), $this->attributes);

        return sprintf('Pins the PHPUnit root attributes: %s.', implode(', ', $names));
    }

    #[\Override]
    public function explain(?string $content): string
    {
        if ($content === null) {
            return 'There is no PHPUnit config yet; one is created holding the pinned attributes.';
        }

        $drifted = [];
        foreach ($this->attributes as $attribute) {
            $written = XmlElementWriter::readAttribute($content, PhpUnitConfigFile::ROOT_ELEMENT, $attribute->name());
            if ($written === $attribute->value()) {
                continue;
            }
            $drifted[] = $written === null
                ? sprintf('%s is not written and is added as "%s"', $attribute->name(), $attribute->value())
                : self::explainDeviation($attribute, $written);
        }
        if ($drifted === []) {
            return 'All pinned attributes carry their required values.';
        }

        return implode('; ', $drifted) . '.';
    }

    private static function explainDeviation(PinnedAttribute $attribute, string $written): string
    {
        // A boolean-shaped pin is the only type evidence available on phpunit's open attribute surface; only then can the spelling trap be named.
        if (in_array($attribute->value(), self::BOOLEAN_SPELLINGS, true) && !in_array(strtolower($written), self::BOOLEAN_SPELLINGS, true)) {
            return sprintf('%s is written as "%s", which phpunit silently reads as false, where "%s" is required', $attribute->name(), $written, $attribute->value());
        }

        return sprintf('%s is written as "%s" where "%s" is required', $attribute->name(), $written, $attribute->value());
    }
}
