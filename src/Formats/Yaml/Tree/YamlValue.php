<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/**
 * A mapping value or a sequence item: what it holds and where its text is.
 * Lines count from zero, and an end is the first line after the value.
 */
final readonly class YamlValue
{
    /**
     * @param string $source the value's own text as written, without a trailing comment; empty for a mapping, a sequence or no value
     * @param string|null $comment the text after the `#` of a comment closing a single-line value, as written
     * @param int $endColumn the column after a single-line value's text
     */
    public function __construct(
        private YamlValueKind $kind,
        private YamlMapping|YamlSequence|null $node,
        private string $source,
        private ?string $comment,
        private int $line,
        private int $startColumn,
        private int $endColumn,
        private int $end,
    ) {}

    public function kind(): YamlValueKind
    {
        return $this->kind;
    }

    public function node(): YamlMapping|YamlSequence|null
    {
        return $this->node;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    /** The line the value's text starts on; for a mapping or a sequence, its first entry's or item's. */
    public function line(): int
    {
        return $this->line;
    }

    public function startColumn(): int
    {
        return $this->startColumn;
    }

    public function endColumn(): int
    {
        return $this->endColumn;
    }

    public function end(): int
    {
        return $this->end;
    }

    public function isMultiline(): bool
    {
        return $this->end - $this->line > 1;
    }

    /** Whether the value is a flow sequence written on one line, whose items can be edited within that line. */
    public function isOneLineFlowSequence(): bool
    {
        return $this->kind === YamlValueKind::Flow && !$this->isMultiline() && str_starts_with($this->source, '[');
    }

    /** What the value means, decoded from its own text alone. */
    public function decoded(): mixed
    {
        if ($this->node !== null) {
            return $this->node->decoded();
        }

        return $this->kind === YamlValueKind::Empty ? null : YamlScalarDecoder::decode($this->source);
    }
}
