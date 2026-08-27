<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Xml;

/** One attribute written on an element's open tag: the raw value, the quote character it is written with, and the spans it occupies in the document. */
final readonly class XmlAttribute
{
    public function __construct(
        private int $start,
        private int $end,
        private string $quote,
        private string $value,
        private int $valueStart,
        private int $valueEnd,
    ) {
    }

    /** The offset of the attribute name; its text runs to the closing quote of its value. */
    public function start(): int
    {
        return $this->start;
    }

    public function end(): int
    {
        return $this->end;
    }

    public function quote(): string
    {
        return $this->quote;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function valueStart(): int
    {
        return $this->valueStart;
    }

    public function valueEnd(): int
    {
        return $this->valueEnd;
    }
}
