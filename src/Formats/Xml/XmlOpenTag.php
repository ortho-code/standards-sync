<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Formats\Xml;

/** An element's open tag as it sits in the document: where it starts, where its attributes begin, and the attributes it writes in document order. */
final readonly class XmlOpenTag
{
    /** @param array<string, XmlAttribute> $attributes keyed by attribute name */
    public function __construct(
        private int $start,
        private int $attributesStart,
        private array $attributes,
    ) {
    }

    /** The offset of the opening "<". */
    public function start(): int
    {
        return $this->start;
    }

    /** The offset just past the element name, where an attribute-less tag takes its first one. */
    public function attributesStart(): int
    {
        return $this->attributesStart;
    }

    public function attribute(string $name): ?XmlAttribute
    {
        return $this->attributes[$name] ?? null;
    }

    public function last(): ?XmlAttribute
    {
        return $this->attributes === [] ? null : $this->attributes[array_key_last($this->attributes)];
    }
}
