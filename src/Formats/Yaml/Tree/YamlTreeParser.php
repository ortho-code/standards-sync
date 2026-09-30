<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

use OrthoCode\StandardsSync\Core\Text\Lines;
use RuntimeException;

/**
 * Locates a document's block structure by line and column, reading every value's extent without decoding the document as a whole.
 * Refuses what it does not handle rather than guessing: anchors, aliases and tags, merge keys, complex keys, duplicate keys, several documents or a directive, tab indentation, and a sequence opening on a key's or a dash's line.
 */
final readonly class YamlTreeParser
{
    /** A double-quoted key and its colon, the quoted text captured. */
    private const string DOUBLE_QUOTED_KEY = '/\G("(?:[^"\\\\]|\\\\.)*")[ \t]*:(?=[ \t]|$)/';

    /** A single-quoted key and its colon, the quoted text captured. */
    private const string SINGLE_QUOTED_KEY = '/\\G(\'(?:[^\']|\'\')*\')[ \\t]*:(?=[ \\t]|$)/';

    /** A plain key and its colon: it may hold spaces, a `#` after a non-space and a colon before a non-space, and never starts with an indicator. */
    private const string PLAIN_KEY = '/\G((?:[^\s\-?:#,\[\]{}&*!|>\'"%@`]|[-?:](?=\S))(?:[^:#\s]|:(?=\S)|(?<=\S)#|[ \t]+(?!:(?:[ \t]|$))(?=[^\s#]))*)[ \t]*:(?=[ \t]|$)/';

    /** A complex key's indicator, `?` followed by whitespace or the end of the line. */
    private const string COMPLEX_KEY = '/\G\?(?:[ \t]|$)/';

    /** A block scalar's header, its chomping and indentation indicators captured. */
    private const string BLOCK_SCALAR_HEADER = '/^[|>]([1-9+-]{0,2})[ \t]*(?:#.*)?$/';

    /** A document marker or a directive at the start of a line. */
    private const string DOCUMENT_MARKER = '/^(?:---|\.\.\.)(?:[ \t]|$)|^%/';

    /** Where a comment opens inside a plain scalar: a `#` after whitespace. */
    private const string PLAIN_COMMENT = '/[ \t]#/';

    private const string DOCUMENT_START = '---';

    private const string MERGE_KEY = '<<';

    private const string KEEP_CHOMPING = '+';

    /** The characters that open an anchor, an alias or a tag. */
    private const array NODE_PROPERTIES = ['&', '*', '!'];

    /** The characters a value on the line after its key may not start with: only a plain scalar is read there. */
    private const array NOT_PLAIN = ['"', '\'', '[', '{', '|', '>', '&', '*', '!'];

    public function __construct(private YamlLines $lines) {}

    public function root(): YamlMapping
    {
        $start = $this->nextContent(0);
        for ($index = 0; $index < $this->lines->count(); $index++) {
            $line = $this->lines->line($index);
            if (preg_match(self::DOCUMENT_MARKER, $line) === 1 && ($index !== $start || trim($line) !== self::DOCUMENT_START)) {
                throw new RuntimeException(sprintf('Line %d starts a document or holds a directive; the YAML reader reads a single document without directives.', $index + 1));
            }
        }
        if ($start < $this->lines->count() && trim($this->lines->line($start)) === self::DOCUMENT_START) {
            $start = $this->nextContent($start + 1);
        }
        if ($start >= $this->lines->count()) {
            throw new RuntimeException('The document is empty; the YAML reader reads a mapping.');
        }
        if ($this->isDash($start)) {
            throw new RuntimeException('The document is a sequence; the YAML reader reads a mapping.');
        }

        $root = $this->mapping($start, $this->indent($start), null);
        $rest = $this->nextContent($root->end());
        if ($rest < $this->lines->count()) {
            throw new RuntimeException(sprintf('Line %d does not belong to the document\'s mapping.', $rest + 1));
        }

        return $root;
    }

    /** @param int|null $dashLine the line of the sequence item the mapping opens on, whose first key stands after the dash */
    private function mapping(int $start, int $indent, ?int $dashLine): YamlMapping
    {
        $entries = [];
        $keys = [];
        $line = $start;
        while (true) {
            $line = $this->nextContent($line);
            if ($line >= $this->lines->count()) {
                break;
            }
            if ($line !== $dashLine) {
                $lineIndent = $this->indent($line);
                if ($lineIndent < $indent || ($lineIndent === $indent && $this->isDash($line))) {
                    break;
                }
                if ($lineIndent > $indent) {
                    throw new RuntimeException(sprintf('Line %d is indented deeper than the mapping it is in.', $line + 1));
                }
            }
            $entry = $this->entry($line, $indent, $line === $dashLine);
            if (in_array($entry->key(), $keys, true)) {
                throw new RuntimeException(sprintf('Line %d repeats the key "%s".', $line + 1, $entry->key()));
            }
            $keys[] = $entry->key();
            $entries[] = $entry;
            $line = $entry->end();
        }

        if ($entries === []) {
            throw new RuntimeException(sprintf('Line %d holds no mapping key where one is expected.', $start + 1));
        }

        return new YamlMapping($indent, $entries, $entries[count($entries) - 1]->end());
    }

    private function entry(int $line, int $column, bool $opensItem): YamlEntry
    {
        $key = $this->key($line, $column)
            ?? throw new RuntimeException(sprintf('Line %d holds no mapping key where one is expected.', $line + 1));
        if ($key[0] === self::MERGE_KEY) {
            throw new RuntimeException(sprintf('Line %d holds a merge key; the YAML reader does not handle merge keys.', $line + 1));
        }

        return new YamlEntry($key[0], $line, $column, $key[1], $this->value($line, $key[1], $column, true), $opensItem);
    }

    /** @return array{string, int}|null the key and the column after its colon */
    private function key(int $line, int $column): ?array
    {
        $text = $this->lines->line($line);
        if (preg_match(self::DOUBLE_QUOTED_KEY, $text, $match, 0, $column) === 1 || preg_match(self::SINGLE_QUOTED_KEY, $text, $match, 0, $column) === 1) {
            $key = YamlScalarDecoder::decode($match[1]);

            return [is_scalar($key) ? (string) $key : $match[1], $column + strlen($match[0])];
        }
        if (preg_match(self::COMPLEX_KEY, $text, $match, 0, $column) === 1) {
            throw new RuntimeException(sprintf('Line %d holds a complex key; the YAML reader does not handle complex keys.', $line + 1));
        }
        if (preg_match(self::PLAIN_KEY, $text, $match, 0, $column) === 1) {
            return [$match[1], $column + strlen($match[0])];
        }

        return null;
    }

    /**
     * The value starting at a column of a line, belonging to a key or a dash at the given indentation.
     *
     * @param bool $inMapping whether the value is a mapping's, which a sequence at the key's own indentation may hold
     */
    private function value(int $line, int $column, int $ownerIndent, bool $inMapping): YamlValue
    {
        $rest = substr($this->lines->line($line), $column);
        $trimmed = ltrim($rest, " \t");
        $start = $column + strlen($rest) - strlen($trimmed);

        if ($trimmed === '' || $trimmed[0] === '#') {
            return $this->valueOnFollowingLines($line, $start, $ownerIndent, $inMapping);
        }
        if (in_array($trimmed[0], self::NODE_PROPERTIES, true)) {
            throw new RuntimeException(sprintf('Line %d holds an anchor, an alias or a tag; the YAML reader does not handle them.', $line + 1));
        }
        if ($trimmed[0] === '-' && (strlen($trimmed) === 1 || $trimmed[1] === ' ' || $trimmed[1] === "\t")) {
            throw new RuntimeException(sprintf('Line %d opens a sequence on a key\'s or a dash\'s line; the YAML reader does not handle it.', $line + 1));
        }
        if (preg_match(self::BLOCK_SCALAR_HEADER, $trimmed, $header) === 1) {
            return $this->blockScalar($line, $start, $ownerIndent, str_contains($header[1], self::KEEP_CHOMPING));
        }
        if ($trimmed[0] === '[' || $trimmed[0] === '{') {
            [$lastLine, $endColumn] = $this->flowEnd($line, $start);

            return $this->closedValue(YamlValueKind::Flow, $line, $start, $lastLine, $endColumn);
        }
        if ($trimmed[0] === '"' || $trimmed[0] === '\'') {
            [$lastLine, $endColumn] = $this->quotedEnd($line, $start);

            return $this->closedValue(YamlValueKind::Quoted, $line, $start, $lastLine, $endColumn);
        }

        return $this->plainScalar($line, $start, $ownerIndent);
    }

    private function valueOnFollowingLines(int $line, int $column, int $ownerIndent, bool $inMapping): YamlValue
    {
        $next = $this->nextContent($line + 1);
        if ($next < $this->lines->count() && $this->indent($next) > $ownerIndent) {
            $indent = $this->indent($next);
            if ($this->isDash($next)) {
                return $this->nodeValue(YamlValueKind::Sequence, $this->sequence($next, $indent, $line + 1));
            }
            if ($this->key($next, $indent) !== null) {
                return $this->nodeValue(YamlValueKind::Mapping, $this->mapping($next, $indent, null));
            }
            if (in_array($this->lines->line($next)[$indent], self::NOT_PLAIN, true)) {
                throw new RuntimeException(sprintf('Line %d starts a value that is not a plain scalar on the line after its key; the YAML reader does not handle it.', $next + 1));
            }

            return $this->plainScalar($next, $indent, $ownerIndent);
        }
        if ($inMapping && $next < $this->lines->count() && $this->indent($next) === $ownerIndent && $this->isDash($next)) {
            return $this->nodeValue(YamlValueKind::Sequence, $this->sequence($next, $ownerIndent, $line + 1));
        }

        return new YamlValue(YamlValueKind::Empty, null, '', null, $line, $column, $column, $line + 1);
    }

    private function sequence(int $start, int $dashColumn, int $leadStart): YamlSequence
    {
        $items = [];
        $line = $start;
        while (true) {
            $line = $this->nextContent($line);
            if ($line >= $this->lines->count()) {
                break;
            }
            $lineIndent = $this->indent($line);
            if ($lineIndent > $dashColumn) {
                throw new RuntimeException(sprintf('Line %d is indented deeper than the sequence it is in.', $line + 1));
            }
            if ($lineIndent < $dashColumn || !$this->isDash($line)) {
                break;
            }
            $item = $this->item($line, $dashColumn);
            $items[] = $item;
            $line = $item->end();
        }

        if ($items === []) {
            throw new RuntimeException(sprintf('Line %d holds no sequence item where one is expected.', $start + 1));
        }

        return new YamlSequence($dashColumn, $items, $leadStart, $items[count($items) - 1]->end());
    }

    private function item(int $line, int $dashColumn): YamlItem
    {
        $text = $this->lines->line($line);
        $contentColumn = $dashColumn + 1 + strspn($text, " \t", $dashColumn + 1);
        $content = substr($text, $contentColumn);

        if ($content !== '' && $content[0] !== '#' && $this->key($line, $contentColumn) !== null) {
            $mapping = $this->mapping($line, $contentColumn, $line);

            return new YamlItem($line, $dashColumn, $contentColumn, new YamlValue(YamlValueKind::Mapping, $mapping, '', null, $line, $contentColumn, $contentColumn, $mapping->end()));
        }

        return new YamlItem($line, $dashColumn, $contentColumn, $this->value($line, $contentColumn, $dashColumn, false));
    }

    private function blockScalar(int $line, int $column, int $ownerIndent, bool $keepsTrailingLines): YamlValue
    {
        $next = $line + 1;
        $last = $line;
        while ($next < $this->lines->count()) {
            if (trim($this->lines->line($next)) === '') {
                $next++;
                continue;
            }
            if ($this->spaces($next) <= $ownerIndent) {
                break;
            }
            $last = $next;
            $next++;
        }

        return $this->spanningValue(YamlValueKind::Block, $line, $column, $keepsTrailingLines ? $next - 1 : $last);
    }

    /** A plain scalar ends at a comment; lines indented deeper than its owner continue it, and a comment line ends it. */
    private function plainScalar(int $line, int $column, int $ownerIndent): YamlValue
    {
        $text = substr($this->lines->line($line), $column);
        $commentAt = preg_match(self::PLAIN_COMMENT, $text, $comment, PREG_OFFSET_CAPTURE) === 1 ? $comment[0][1] : null;
        $last = $line;
        if ($commentAt === null) {
            for ($next = $line + 1; $next < $this->lines->count(); $next++) {
                $trimmed = trim($this->lines->line($next));
                if ($trimmed === '') {
                    continue;
                }
                if ($trimmed[0] === '#' || $this->spaces($next) <= $ownerIndent) {
                    break;
                }
                $last = $next;
            }
        }
        if ($last > $line) {
            return $this->spanningValue(YamlValueKind::Plain, $line, $column, $last);
        }

        $value = rtrim($commentAt === null ? $text : substr($text, 0, $commentAt));

        return $this->closedValue(YamlValueKind::Plain, $line, $column, $line, $column + strlen($value));
    }

    /** A value whose text ends at a known column, followed by nothing but a comment. */
    private function closedValue(YamlValueKind $kind, int $line, int $column, int $lastLine, int $endColumn): YamlValue
    {
        $after = trim(substr($this->lines->line($lastLine), $endColumn));
        if ($after !== '' && $after[0] !== '#') {
            throw new RuntimeException(sprintf('Line %d holds more after a closed value.', $lastLine + 1));
        }
        if ($lastLine > $line) {
            return $this->spanningValue($kind, $line, $column, $lastLine);
        }

        return new YamlValue(
            $kind,
            null,
            substr($this->lines->line($line), $column, $endColumn - $column),
            $after === '' ? null : substr($after, 1),
            $line,
            $column,
            $endColumn,
            $line + 1,
        );
    }

    /** A value spanning lines: its text runs from its column to the end of its last line, that line's break included. */
    private function spanningValue(YamlValueKind $kind, int $line, int $column, int $lastLine): YamlValue
    {
        $texts = [substr($this->lines->line($line), $column)];
        for ($next = $line + 1; $next <= $lastLine; $next++) {
            $texts[] = $this->lines->line($next);
        }
        $source = Lines::join($texts) . ($this->lines->endsWithBreak($lastLine) ? Lines::LINE_BREAK : '');

        return new YamlValue($kind, null, $source, null, $line, $column, strlen($this->lines->line($lastLine)), $lastLine + 1);
    }

    private function nodeValue(YamlValueKind $kind, YamlMapping|YamlSequence $node): YamlValue
    {
        $first = $node instanceof YamlMapping ? $node->entries()[0]->line() : $node->items()[0]->line();
        $column = $node instanceof YamlMapping ? $node->indent() : $node->dashColumn();

        return new YamlValue($kind, $node, '', null, $first, $column, $column, $node->end());
    }

    /** @return array{int, int} the line of the closing quote and the column after it */
    private function quotedEnd(int $line, int $column): array
    {
        $quote = $this->lines->line($line)[$column];
        $position = $column + 1;
        for ($current = $line; $current < $this->lines->count(); $current++) {
            $text = $this->lines->line($current);
            while ($position < strlen($text)) {
                $character = $text[$position];
                if ($quote === '"' && $character === '\\') {
                    $position += 2;
                    continue;
                }
                if ($character === $quote) {
                    if ($quote === '\'' && ($text[$position + 1] ?? '') === '\'') {
                        $position += 2;
                        continue;
                    }

                    return [$current, $position + 1];
                }
                $position++;
            }
            $position = 0;
        }

        throw new RuntimeException(sprintf('Line %d opens a quoted value that never closes.', $line + 1));
    }

    /** @return array{int, int} the line of the closing bracket and the column after it */
    private function flowEnd(int $line, int $column): array
    {
        $depth = 0;
        $quote = null;
        $position = $column;
        for ($current = $line; $current < $this->lines->count(); $current++) {
            $text = $this->lines->line($current);
            while ($position < strlen($text)) {
                $character = $text[$position];
                if ($quote !== null) {
                    if ($quote === '"' && $character === '\\') {
                        $position += 2;
                        continue;
                    }
                    if ($character === $quote) {
                        $quote = null;
                    }
                } elseif ($character === '"' || $character === '\'') {
                    $quote = $character;
                } elseif ($character === '#' && $position > 0 && ($text[$position - 1] === ' ' || $text[$position - 1] === "\t")) {
                    break;
                } elseif ($character === '[' || $character === '{') {
                    $depth++;
                } elseif ($character === ']' || $character === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return [$current, $position + 1];
                    }
                }
                $position++;
            }
            $position = 0;
        }

        throw new RuntimeException(sprintf('Line %d opens a flow collection that never closes.', $line + 1));
    }

    /** The first line from the given one holding more than whitespace or a comment. */
    private function nextContent(int $line): int
    {
        for (; $line < $this->lines->count(); $line++) {
            $trimmed = trim($this->lines->line($line));
            if ($trimmed !== '' && $trimmed[0] !== '#') {
                return $line;
            }
        }

        return $this->lines->count();
    }

    /** A structural line's indentation, which YAML allows only in spaces. */
    private function indent(int $line): int
    {
        $spaces = $this->spaces($line);
        if (($this->lines->line($line)[$spaces] ?? '') === "\t") {
            throw new RuntimeException(sprintf('Line %d is indented with a tab, which YAML does not allow.', $line + 1));
        }

        return $spaces;
    }

    private function spaces(int $line): int
    {
        return strspn($this->lines->line($line), ' ');
    }

    private function isDash(int $line): bool
    {
        $rest = ltrim($this->lines->line($line), ' ');

        return $rest === '-' || str_starts_with($rest, '- ') || str_starts_with($rest, "-\t");
    }
}
