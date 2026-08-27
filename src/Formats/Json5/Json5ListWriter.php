<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Json5;

use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Formats\Json\JsonMember;
use OrthoCode\StandardsSync\Formats\Json\JsonObject;
use RuntimeException;

/**
 * Ensures one string entry in a top-level member's list in JSON5 text, with an optional enforced trailing comment on the entry's line.
 * Entries and keys are matched on their unquoted value, so two spellings of one string are the same; insertion copies the sibling entries' quote and trailing-comma style.
 * Targeted span edits only — comments, key spellings and everything else around the touched span stay byte-identical; no parse, no reserialization.
 */
final readonly class Json5ListWriter
{
    /** The indentation of a created document; the width shared with the layer's JSON member. */
    private const string DEFAULT_INDENT = '    ';

    /** The canonical separator between an entry and its enforced trailing comment. */
    private const string COMMENT_LEAD = ' // ';

    /** The ASCII identifier characters of an unquoted JSON5 key; exotic unicode keys are quoted in practice. */
    private const string IDENTIFIER_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_$';

    private const string WHITESPACE = " \t\r" . Lines::LINE_BREAK;

    /** How a double-quoted string renders; the flags shared with the layer's JSON member. */
    private const int RENDER_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    private const string BLOCK_COMMENT_OPEN = '/*';
    private const string BLOCK_COMMENT_CLOSE = '*/';

    /**
     * Ensures the entry in the key's list: a present entry is kept, an absent one is appended in the list's own layout, a missing key is appended to the object, and empty content becomes a document holding just the entry.
     * With $comment the entry's line end is owned — a missing or deviating trailing comment is rewritten; without, a project's own trailing comment survives.
     * The comment needs a line position that reads as the entry's own, so an entry that does not end its line carries none.
     */
    public static function ensureEntry(string $content, string $key, string $entry, ?string $comment = null): string
    {
        if (trim($content) === '') {
            return self::createDocument($key, $entry, $comment);
        }

        $objectStart = self::skipInsignificant($content, 0);
        if (self::characterAt($content, $objectStart) !== '{') {
            throw new RuntimeException('The file does not hold a JSON5 object; it cannot be managed.');
        }

        $object = self::scanObject($content, $objectStart);
        $member = $object->member($key);
        if ($member === null) {
            return self::appendMember($content, $object, $key, $entry, $comment);
        }

        if (self::characterAt($content, $member->valueStart()) !== '[') {
            throw new RuntimeException(sprintf('"%s" does not hold a list; "%s" cannot be ensured in it.', $key, $entry));
        }

        $spans = self::scanList($content, $member->valueStart());
        $present = array_find($spans, static fn (array $span): bool => self::isEntry($content, $span, $entry));
        if ($present !== null) {
            return $comment === null ? $content : self::ensureTrailingComment($content, $present, $comment);
        }

        return self::insertEntry($content, $member, $spans, $entry, $comment);
    }

    private static function createDocument(string $key, string $entry, ?string $comment): string
    {
        return '{' . Lines::LINE_BREAK
            . self::DEFAULT_INDENT . self::renderDouble($key) . ': [' . Lines::LINE_BREAK
            . self::DEFAULT_INDENT . self::DEFAULT_INDENT . self::renderDouble($entry) . self::renderComment($comment) . Lines::LINE_BREAK
            . self::DEFAULT_INDENT . ']' . Lines::LINE_BREAK
            . '}' . Lines::LINE_BREAK;
    }

    /** @param list<array{int, int}> $spans */
    private static function insertEntry(string $content, JsonMember $member, array $spans, string $entry, ?string $comment): string
    {
        if ($spans === []) {
            // A single entry joins an empty list inline: the smallest edit, whatever the surrounding layout.
            return substr($content, 0, $member->valueStart())
                . '[' . self::render($content, $spans, $entry) . ']'
                . substr($content, $member->valueEnd());
        }

        [$lastStart, $lastEnd] = $spans[count($spans) - 1];
        $rendered = self::render($content, $spans, $entry);

        // The closing bracket shares the last entry's line: append inline — a line-anchored insertion would land past the bracket.
        if (self::lineStart($content, $lastStart) === self::lineStart($content, $member->valueEnd() - 1)) {
            return substr($content, 0, $lastEnd) . ', ' . $rendered . substr($content, $lastEnd);
        }

        $lineEnd = self::lineEnd($content, $lastEnd);
        $afterLast = self::skipInsignificant($content, $lastEnd);
        // A comma on a later line is not the list's trailing-comma style: the separator is inserted here and that comma trails the new entry.
        $trailingComma = self::characterAt($content, $afterLast) === ',' && $afterLast < $lineEnd;

        return substr($content, 0, $lastEnd)
            . ($trailingComma ? '' : ',')
            . substr($content, $lastEnd, $lineEnd - $lastEnd)
            . Lines::LINE_BREAK . self::lineIndent($content, $lastStart) . $rendered . ($trailingComma ? ',' : '') . self::renderComment($comment)
            . substr($content, $lineEnd);
    }

    private static function appendMember(string $content, JsonObject $object, string $key, string $entry, ?string $comment): string
    {
        $unit = self::detectIndent($content);
        $last = $object->last();

        // An object with nothing to preserve is filled in the canonical shape, one member per line.
        if ($last === null) {
            $closingIndent = self::lineIndent($content, $object->start());
            $memberIndent = $closingIndent . $unit;

            return substr($content, 0, $object->start() + 1)
                . Lines::LINE_BREAK . $memberIndent . self::renderDouble($key) . ': [' . Lines::LINE_BREAK
                . $memberIndent . $unit . self::renderDouble($entry) . self::renderComment($comment) . Lines::LINE_BREAK
                . $memberIndent . ']'
                . Lines::LINE_BREAK . $closingIndent
                . substr($content, $object->end());
        }

        $renderedKey = self::renderKeyLike($content, $last->start(), $key);

        // The closing brace shares the last member's line: append inline — a line-anchored insertion would land past the brace.
        if (self::lineStart($content, $last->start()) === self::lineStart($content, $object->end())) {
            return substr($content, 0, $last->valueEnd())
                . ', ' . $renderedKey . ': [' . self::renderDouble($entry) . ']'
                . substr($content, $last->valueEnd());
        }

        $memberIndent = self::lineIndent($content, $last->start());
        $lineEnd = self::lineEnd($content, $last->valueEnd());
        $afterLast = self::skipInsignificant($content, $last->valueEnd());
        // A comma on a later line is not the object's trailing-comma style: the separator is inserted here and that comma trails the new member.
        $trailingComma = self::characterAt($content, $afterLast) === ',' && $afterLast < $lineEnd;

        return substr($content, 0, $last->valueEnd())
            . ($trailingComma ? '' : ',')
            . substr($content, $last->valueEnd(), $lineEnd - $last->valueEnd())
            . Lines::LINE_BREAK . $memberIndent . $renderedKey . ': [' . Lines::LINE_BREAK
            . $memberIndent . $unit . self::renderDouble($entry) . self::renderComment($comment) . Lines::LINE_BREAK
            . $memberIndent . ']' . ($trailingComma ? ',' : '')
            . substr($content, $lineEnd);
    }

    /**
     * Enforces the trailing comment on the entry's line, leaving the entry and its comma verbatim; a line already carrying the canonical comment stays put.
     * An entry that does not end its line has no position that reads as its own comment, so the content is returned unchanged.
     *
     * @param array{int, int} $span
     */
    private static function ensureTrailingComment(string $content, array $span, string $comment): string
    {
        [, $end] = $span;
        $lineEnd = self::lineEnd($content, $end);
        $tail = substr($content, $end, $lineEnd - $end);

        if (preg_match('~^(?<lead>[ \t]*,)?[ \t]*(?://.*)?$~', $tail, $match) !== 1) {
            return $content;
        }

        $expected = (($match['lead'] ?? '') !== '' ? ',' : '') . self::COMMENT_LEAD . $comment;
        if ($tail === $expected) {
            return $content;
        }

        return substr($content, 0, $end) . $expected . substr($content, $lineEnd);
    }

    /** Tokenizes one object's members in document order, keyed by their unquoted name. */
    private static function scanObject(string $content, int $start): JsonObject
    {
        $members = [];
        $index = self::skipInsignificant($content, $start + 1);
        while (self::characterAt($content, $index) !== '}') {
            if (self::characterAt($content, $index) === '') {
                throw new RuntimeException('A JSON5 object or list never closes; the file cannot be managed.');
            }

            $keyStart = $index;
            $index = self::skipKey($content, $index);
            $key = self::memberKey(substr($content, $keyStart, $index - $keyStart));

            $colon = self::skipInsignificant($content, $index);
            if (self::characterAt($content, $colon) !== ':') {
                throw new RuntimeException(sprintf('The JSON5 member "%s" has no value; the file cannot be managed.', $key));
            }
            $valueStart = self::skipInsignificant($content, $colon + 1);
            $index = self::skipValue($content, $valueStart);

            if (isset($members[$key])) {
                throw new RuntimeException(sprintf('The JSON5 object sets "%s" more than once; the file cannot be managed.', $key));
            }
            $members[$key] = new JsonMember($keyStart, $valueStart, $index);

            $index = self::skipInsignificant($content, $index);
            if (self::characterAt($content, $index) === ',') {
                $index = self::skipInsignificant($content, $index + 1);
            }
        }

        return new JsonObject($start, $index, $members);
    }

    /** @return list<array{int, int}> */
    private static function scanList(string $content, int $start): array
    {
        $spans = [];
        $index = self::skipInsignificant($content, $start + 1);
        while (self::characterAt($content, $index) !== ']') {
            if (self::characterAt($content, $index) === '') {
                throw new RuntimeException('A JSON5 object or list never closes; the file cannot be managed.');
            }

            $valueStart = $index;
            $index = self::skipValue($content, $index);
            $spans[] = [$valueStart, $index];

            $index = self::skipInsignificant($content, $index);
            if (self::characterAt($content, $index) === ',') {
                $index = self::skipInsignificant($content, $index + 1);
            }
        }

        return $spans;
    }

    /** @param array{int, int} $span */
    private static function isEntry(string $content, array $span, string $entry): bool
    {
        $character = $content[$span[0]];
        if ($character !== '"' && $character !== "'") {
            return false;
        }

        return self::unquote(substr($content, $span[0], $span[1] - $span[0])) === $entry;
    }

    /**
     * The string a quoted span spells, common escapes resolved.
     * An exotic escape spelling mismatches and adds a visible duplicate once — never a bad edit.
     */
    private static function unquote(string $text): string
    {
        return stripcslashes(substr($text, 1, -1));
    }

    /**
     * Renders the entry in the sibling entries' quote style, so an addition reads like the list it joins.
     *
     * @param list<array{int, int}> $spans
     */
    private static function render(string $content, array $spans, string $entry): string
    {
        $span = $spans === [] ? null : $spans[count($spans) - 1];

        return $span === null ? self::renderDouble($entry) : self::renderLike($content, $span[0], $entry);
    }

    private static function renderLike(string $content, int $offset, string $text): string
    {
        if ($content[$offset] === "'") {
            return "'" . addcslashes($text, "\\'") . "'";
        }

        return self::renderDouble($text);
    }

    /** Renders the key in the sibling member's spelling — bare where the file uses bare identifiers and the key allows it. */
    private static function renderKeyLike(string $content, int $offset, string $key): string
    {
        if ($content[$offset] === "'") {
            return "'" . addcslashes($key, "\\'") . "'";
        }

        if ($content[$offset] !== '"' && strspn($key, self::IDENTIFIER_CHARACTERS) === strlen($key)) {
            return $key;
        }

        return self::renderDouble($key);
    }

    private static function renderDouble(string $text): string
    {
        return json_encode($text, self::RENDER_FLAGS);
    }

    private static function renderComment(?string $comment): string
    {
        return $comment === null ? '' : self::COMMENT_LEAD . $comment;
    }

    private static function skipKey(string $content, int $index): int
    {
        $character = self::characterAt($content, $index);
        if ($character === '"' || $character === "'") {
            return self::skipString($content, $index);
        }

        $length = strspn($content, self::IDENTIFIER_CHARACTERS, $index);
        if ($length === 0) {
            throw new RuntimeException(sprintf('Unexpected "%s" where a JSON5 member key should start; the file cannot be managed.', $character));
        }

        return $index + $length;
    }

    private static function memberKey(string $text): string
    {
        return $text[0] === '"' || $text[0] === "'" ? self::unquote($text) : $text;
    }

    private static function skipValue(string $content, int $index): int
    {
        return match (self::characterAt($content, $index)) {
            '"', "'" => self::skipString($content, $index),
            '{', '[' => self::skipBracketed($content, $index),
            '' => throw new RuntimeException('The JSON5 document ends where a value should start; the file cannot be managed.'),
            default => self::skipLiteral($content, $index),
        };
    }

    /** The offset just past the string starting at $index; a closing quote must not be escaped. */
    private static function skipString(string $content, int $index): int
    {
        $quote = $content[$index];
        for ($cursor = $index + 1; $cursor < strlen($content); $cursor++) {
            if ($content[$cursor] === '\\') {
                $cursor++;
                continue;
            }
            if ($content[$cursor] === $quote) {
                return $cursor + 1;
            }
        }

        throw new RuntimeException('A JSON5 string never closes; the file cannot be managed.');
    }

    /** The offset just past the object or list starting at $index; brackets inside strings and comments do not count. */
    private static function skipBracketed(string $content, int $index): int
    {
        $depth = 0;
        for ($cursor = $index; $cursor < strlen($content); $cursor++) {
            $character = $content[$cursor];
            if ($character === '"' || $character === "'") {
                $cursor = self::skipString($content, $cursor) - 1;
                continue;
            }
            if ($character === '/') {
                $cursor = self::skipComment($content, $cursor) - 1;
                continue;
            }
            if ($character === '{' || $character === '[') {
                $depth++;
                continue;
            }
            if ($character === '}' || $character === ']') {
                $depth--;
                if ($depth === 0) {
                    return $cursor + 1;
                }
            }
        }

        throw new RuntimeException('A JSON5 object or list never closes; the file cannot be managed.');
    }

    private static function skipLiteral(string $content, int $index): int
    {
        return $index + strcspn($content, ',}]/' . self::WHITESPACE, $index);
    }

    /** The offset just past the comment starting at $index. */
    private static function skipComment(string $content, int $index): int
    {
        $next = self::characterAt($content, $index + 1);
        if ($next === '/') {
            $lineBreak = strpos($content, Lines::LINE_BREAK, $index);

            return $lineBreak === false ? strlen($content) : $lineBreak;
        }

        if ($next === '*') {
            $close = strpos($content, self::BLOCK_COMMENT_CLOSE, $index + strlen(self::BLOCK_COMMENT_OPEN));
            if ($close === false) {
                throw new RuntimeException('A JSON5 comment never closes; the file cannot be managed.');
            }

            return $close + strlen(self::BLOCK_COMMENT_CLOSE);
        }

        throw new RuntimeException('Unexpected "/" outside a JSON5 comment; the file cannot be managed.');
    }

    /** The offset past all whitespace and comments from $index on. */
    private static function skipInsignificant(string $content, int $index): int
    {
        while (true) {
            $index += strspn($content, self::WHITESPACE, $index);
            if (self::characterAt($content, $index) !== '/') {
                return $index;
            }

            $index = self::skipComment($content, $index);
        }
    }

    /** The file's own indentation unit, copied from its first indented line. */
    private static function detectIndent(string $content): string
    {
        return preg_match('/\n([ \t]+)\S/', $content, $match) === 1 ? $match[1] : self::DEFAULT_INDENT;
    }

    private static function lineIndent(string $content, int $offset): string
    {
        preg_match('/^[ \t]*/', substr($content, self::lineStart($content, $offset)), $match);

        return $match[0];
    }

    private static function lineStart(string $content, int $offset): int
    {
        $break = strrpos(substr($content, 0, $offset), Lines::LINE_BREAK);

        return $break === false ? 0 : $break + 1;
    }

    private static function lineEnd(string $content, int $offset): int
    {
        $break = strpos($content, Lines::LINE_BREAK, $offset);

        return $break === false ? strlen($content) : $break;
    }

    private static function characterAt(string $content, int $index): string
    {
        return $index < strlen($content) ? $content[$index] : '';
    }
}
