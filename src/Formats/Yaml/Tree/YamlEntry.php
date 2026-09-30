<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/** A mapping key, where it stands, and its value. */
final readonly class YamlEntry
{
    /**
     * @param int $keyEnd the column after the key's colon
     * @param bool $opensItem whether the key stands on its sequence item's dash line, as in `- key: value`
     */
    public function __construct(
        private string $key,
        private int $line,
        private int $column,
        private int $keyEnd,
        private YamlValue $value,
        private bool $opensItem,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function line(): int
    {
        return $this->line;
    }

    public function column(): int
    {
        return $this->column;
    }

    public function keyEnd(): int
    {
        return $this->keyEnd;
    }

    public function value(): YamlValue
    {
        return $this->value;
    }

    public function opensItem(): bool
    {
        return $this->opensItem;
    }

    /** The first line after the entry's value. */
    public function end(): int
    {
        return $this->value->end();
    }
}
