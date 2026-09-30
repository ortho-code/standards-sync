<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml;

use LogicException;
use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlMapping;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlSequence;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValue;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlValueKind;

/**
 * An entry, an item or a value taken from a source document, to be written into another document in that document's own style.
 * Nesting steps in by the target's unit and dash style; a value spanning lines keeps each line's indentation relative to its owner; the comment lines directly above an entry or an item go with it.
 */
final readonly class YamlFragment
{
    /** A plain key that needs no quotes to read back as itself. */
    private const string PLAIN_KEY = '/^[A-Za-z_][A-Za-z0-9_.\/-]*$/';

    /** The key a scalar item is parsed under, as a document of one sequence. */
    private const string ITEMS_KEY = 'items';

    /** @param bool $valueOnly whether the fragment is the entry's value alone, without its key */
    private function __construct(
        private YamlTree $source,
        private YamlEntry|YamlItem $node,
        private bool $valueOnly,
    ) {}

    public static function fromEntry(YamlTree $source, YamlEntry $entry): self
    {
        return new self($source, $entry, false);
    }

    public static function fromItem(YamlTree $source, YamlItem $item): self
    {
        return new self($source, $item, false);
    }

    /** The entry's value alone, to take the place of another entry's value. */
    public static function fromValue(YamlTree $source, YamlEntry $entry): self
    {
        return new self($source, $entry, true);
    }

    /** An entry of a key and a single-line scalar, the scalar written as its source reads. */
    public static function fromScalarEntry(string $key, string $source): self
    {
        $spelled = preg_match(self::PLAIN_KEY, $key) === 1 ? $key : '\'' . str_replace('\'', '\'\'', $key) . '\'';
        $tree = YamlTree::fromString($spelled . ': ' . $source . Lines::LINE_BREAK);

        return new self($tree, $tree->root()->entries()[0], false);
    }

    /** A sequence item holding a single-line scalar, the scalar written as its source reads. */
    public static function fromScalarItem(string $source): self
    {
        $tree = YamlTree::fromString(self::ITEMS_KEY . ':' . Lines::LINE_BREAK . '  - ' . $source . Lines::LINE_BREAK);
        $sequence = $tree->root()->entries()[0]->value()->node();
        if (!$sequence instanceof YamlSequence) {
            throw new LogicException('A scalar item always parses as a sequence of one.');
        }

        return new self($tree, $sequence->items()[0], false);
    }

    /**
     * The entry's lines with its key in the given column, the comment lines directly above it first.
     *
     * @return list<string>
     */
    public function entryLines(YamlStyle $style, int $column): array
    {
        if (!$this->node instanceof YamlEntry || $this->valueOnly) {
            throw new LogicException('Only an entry fragment renders as an entry.');
        }

        return [
            ...$this->commentsAbove($this->node->line(), $this->node->column(), $column),
            ...$this->entry($this->node, $column, $style),
        ];
    }

    /**
     * The item's lines with its dash in the given column and its content the given offset after it, the comment lines directly above it first.
     *
     * @return list<string>
     */
    public function itemLines(YamlStyle $style, int $dashColumn, int $itemOffset): array
    {
        if (!$this->node instanceof YamlItem) {
            throw new LogicException('Only an item fragment renders as an item.');
        }

        return [
            ...$this->commentsAbove($this->node->line(), $this->node->dashColumn(), $dashColumn),
            ...$this->item($this->node, $dashColumn, $itemOffset, $style),
        ];
    }

    /**
     * The value for a key in the given column: the text to follow the key's colon on its own line, and the lines after that one.
     *
     * @return array{string, list<string>}
     */
    public function valueLines(YamlStyle $style, int $keyColumn): array
    {
        if (!$this->node instanceof YamlEntry || !$this->valueOnly) {
            throw new LogicException('Only a value fragment renders as a value.');
        }

        $value = $this->node->value();

        return [
            $this->inline($value, $this->node->line()),
            $this->following($value, $this->node->line(), $this->node->column(), $keyColumn, $style),
        ];
    }

    /** @return list<string> */
    private function entry(YamlEntry $entry, int $column, YamlStyle $style): array
    {
        $first = str_repeat(' ', $column) . substr($this->source->lines()->line($entry->line()), $entry->column());

        return [$first, ...$this->following($entry->value(), $entry->line(), $entry->column(), $column, $style)];
    }

    /** @return list<string> */
    private function item(YamlItem $item, int $dashColumn, int $itemOffset, YamlStyle $style): array
    {
        $prefix = str_repeat(' ', $dashColumn) . '-' . str_repeat(' ', $itemOffset - 1);
        $value = $item->value();
        $node = $value->node();
        if ($node instanceof YamlMapping && $value->line() === $item->line()) {
            $contentColumn = $dashColumn + $itemOffset;
            $lines = $this->mapping($node, $item->line(), $contentColumn, $style);
            $lines[0] = $prefix . substr($lines[0], $contentColumn);

            return $lines;
        }

        $content = substr($this->source->lines()->line($item->line()), $item->contentColumn());
        $first = $content === '' ? rtrim($prefix) : $prefix . $content;

        return [$first, ...$this->following($value, $item->line(), $item->dashColumn(), $dashColumn, $style)];
    }

    /**
     * A mapping's entries in the given column, with the comment and blank lines leading each.
     *
     * @return list<string>
     */
    private function mapping(YamlMapping $mapping, int $leadFrom, int $column, YamlStyle $style): array
    {
        $lines = [];
        $previousEnd = $leadFrom;
        foreach ($mapping->entries() as $entry) {
            array_push($lines, ...$this->leads($previousEnd, $entry->line(), $column));
            array_push($lines, ...$this->entry($entry, $column, $style));
            $previousEnd = $entry->end();
        }

        return $lines;
    }

    /**
     * A sequence's items with their dashes in the given column, with the comment and blank lines leading each.
     *
     * @return list<string>
     */
    private function sequence(YamlSequence $sequence, int $dashColumn, YamlStyle $style): array
    {
        $lines = [];
        $previousEnd = $sequence->leadStart();
        foreach ($sequence->items() as $item) {
            array_push($lines, ...$this->leads($previousEnd, $item->line(), $dashColumn));
            array_push($lines, ...$this->item($item, $dashColumn, $style->itemOffset(), $style));
            $previousEnd = $item->end();
        }

        return $lines;
    }

    /**
     * The lines of a value after its owner's line: a nested mapping or sequence in the target's style, or the further lines of a scalar shifted with their owner.
     *
     * @return list<string>
     */
    private function following(YamlValue $value, int $ownerLine, int $ownerSourceColumn, int $ownerColumn, YamlStyle $style): array
    {
        $node = $value->node();
        if ($node instanceof YamlMapping) {
            return $this->mapping($node, $ownerLine + 1, $ownerColumn + $style->unit(), $style);
        }
        if ($node instanceof YamlSequence) {
            return $this->sequence($node, $ownerColumn + $style->sequenceOffset(), $style);
        }

        $shift = $ownerColumn - $ownerSourceColumn;
        $lines = [];
        for ($line = $ownerLine + 1; $line < $value->end(); $line++) {
            $lines[] = self::shifted($this->source->lines()->line($line), $shift);
        }

        return $lines;
    }

    /** The text following a key's colon on its own line: the value when it starts there, with any comment after it; nothing for a nested mapping or sequence. */
    private function inline(YamlValue $value, int $ownerLine): string
    {
        if ($value->node() !== null || $value->kind() === YamlValueKind::Empty || $value->line() !== $ownerLine) {
            return '';
        }

        return substr($this->source->lines()->line($ownerLine), $value->startColumn());
    }

    /**
     * The comment lines directly above a line, no blank line between, standing no further right than their node.
     *
     * @return list<string>
     */
    private function commentsAbove(int $line, int $sourceColumn, int $column): array
    {
        $lines = $this->source->lines();
        $first = $line;
        while ($first > 0) {
            $text = $lines->line($first - 1);
            $trimmed = ltrim($text, ' ');
            if ($trimmed === '' || $trimmed[0] !== '#' || strlen($text) - strlen($trimmed) > $sourceColumn) {
                break;
            }
            $first--;
        }

        return $this->leads($first, $line, $column);
    }

    /**
     * The comment and blank lines between two lines, comments moved to the given column and blank lines left empty.
     *
     * @return list<string>
     */
    private function leads(int $from, int $to, int $column): array
    {
        $lines = [];
        for ($line = $from; $line < $to; $line++) {
            $trimmed = trim($this->source->lines()->line($line));
            $lines[] = $trimmed === '' ? '' : str_repeat(' ', $column) . $trimmed;
        }

        return $lines;
    }

    /** A line moved right or left by the given number of columns; a line of only whitespace comes out empty. */
    private static function shifted(string $text, int $shift): string
    {
        if (trim($text) === '') {
            return '';
        }
        $spaces = strspn($text, ' ');

        return str_repeat(' ', max(0, $spaces + $shift)) . substr($text, $spaces);
    }
}
