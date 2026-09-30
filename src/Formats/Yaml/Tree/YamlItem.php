<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/** A sequence item: its dash, where its content starts, and its value. */
final readonly class YamlItem
{
    /** @param int $contentColumn the column after the dash and the spaces following it; a mapping item's keys all stand in it */
    public function __construct(
        private int $line,
        private int $dashColumn,
        private int $contentColumn,
        private YamlValue $value,
    ) {}

    public function line(): int
    {
        return $this->line;
    }

    public function dashColumn(): int
    {
        return $this->dashColumn;
    }

    public function contentColumn(): int
    {
        return $this->contentColumn;
    }

    public function value(): YamlValue
    {
        return $this->value;
    }

    /** The first line after the item's value. */
    public function end(): int
    {
        return $this->value->end();
    }
}
