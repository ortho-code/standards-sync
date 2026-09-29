<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Json;

use OrthoCode\StandardsSync\Core\Text\Lines;
use JsonException;
use RuntimeException;
use stdClass;

/**
 * Reads, sets or removes one member at a nested key path in JSON object text, creating missing objects along the way when writing.
 * Targeted span edits only — everything around the touched member stays byte-identical; no parse, no reserialization.
 * Keys are compared decoded, so two spellings of one key are the same member; values are compared decoded too, so a differently laid out but equal value is left alone.
 * The document is validated before any scan, so every refusal below names a real problem rather than a parser limit.
 */
final readonly class JsonObjectWriter
{
    /** The indentation of a file that carries no indented member to copy; also the tools' own generated width. */
    private const string DEFAULT_INDENT = '    ';

    private const string WHITESPACE = " \t\r" . Lines::LINE_BREAK;

    /** Slashes and unicode stay literal, so a written value reads in the file exactly as it was declared. */
    private const int RENDER_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * The string written at a nested key path, or null when the path is not written.
     *
     * @param non-empty-list<string> $path
     */
    public static function read(string $content, array $path): ?string
    {
        [, $member] = self::walk($content, $path);
        if (!$member instanceof JsonMember) {
            return null;
        }

        $value = $member->valueIn($content);
        if (!is_string($value)) {
            throw new RuntimeException(sprintf('"%s" does not hold a string; it cannot be read as one.', implode('.', $path)));
        }

        return $value;
    }

    /**
     * The strings at a nested key path as a list, or null when the path is not written; a lone string reads as a one-entry list, the one-or-many shorthand JSON configs commonly accept.
     *
     * @param non-empty-list<string> $path
     * @return list<string>|null
     */
    public static function readList(string $content, array $path): ?array
    {
        [, $member] = self::walk($content, $path);
        if (!$member instanceof JsonMember) {
            return null;
        }

        $value = $member->valueIn($content);
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value) || !array_is_list($value) || !array_all($value, static fn(mixed $entry): bool => is_string($entry))) {
            throw new RuntimeException(sprintf('"%s" does not hold a string or a list of strings; it cannot be read as a list.', implode('.', $path)));
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * Sets a scalar at a nested key path: an existing member's value is replaced, a missing one is inserted, and an equal value leaves the content untouched.
     *
     * @param non-empty-list<string> $path
     */
    public static function write(string $content, array $path, string|bool|int $value): string
    {
        return self::set($content, $path, $value);
    }

    /**
     * Sets a list of strings at a nested key path, rendered one entry per line in the file's own indentation.
     *
     * @param non-empty-list<string> $path
     * @param non-empty-list<string> $values
     */
    public static function writeList(string $content, array $path, array $values): string
    {
        return self::set($content, $path, $values);
    }

    /**
     * Ensures one string entry in the list at a nested key path: a present entry is kept byte-identical, an absent one is appended in the list's own layout, and a missing member is created holding just the entry.
     *
     * @param non-empty-list<string> $path
     */
    public static function ensureListEntry(string $content, array $path, string $entry): string
    {
        [, $member] = self::walk($content, $path);
        if (!$member instanceof JsonMember) {
            return self::set($content, $path, [$entry]);
        }

        if ($content[$member->valueStart()] !== '[') {
            throw new RuntimeException(sprintf('"%s" does not hold a list; "%s" cannot be ensured in it.', implode('.', $path), $entry));
        }

        $entries = self::scanList($content, $member->valueStart());
        if (array_any($entries, static fn(array $span): bool => $content[$span[0]] === '"' && self::decode(substr($content, $span[0], $span[1] - $span[0])) === $entry)) {
            return $content;
        }

        if ($entries === []) {
            // A single entry joins an empty list inline: the smallest edit, whatever the surrounding layout.
            return substr($content, 0, $member->valueStart()) . '[' . self::encode($entry) . ']' . substr($content, $member->valueEnd());
        }

        [$lastStart, $lastEnd] = $entries[count($entries) - 1];
        if (self::lineStart($content, $lastStart) === self::lineStart($content, $member->valueStart())) {
            return substr($content, 0, $lastEnd) . ', ' . self::encode($entry) . substr($content, $lastEnd);
        }

        return substr($content, 0, $lastEnd)
            . ',' . Lines::LINE_BREAK . self::lineIndent($content, $lastStart) . self::encode($entry)
            . substr($content, $lastEnd);
    }

    /**
     * Removes the member at a nested key path along with its separating comma, leaving an absent path untouched.
     * An object losing its last member collapses to "{}" rather than keeping the empty lines its member stood on.
     *
     * @param non-empty-list<string> $path
     */
    public static function remove(string $content, array $path): string
    {
        [$object, $member] = self::walk($content, $path);
        if (!$member instanceof JsonMember) {
            return $content;
        }

        if (count($object->members()) === 1) {
            return substr($content, 0, $object->start() + 1) . substr($content, $object->end());
        }

        $following = self::skipWhitespace($content, $member->valueEnd());
        $lineStart = self::lineStart($content, $member->start());
        $ownsItsLine = trim(substr($content, $lineStart, $member->start() - $lineStart)) === '';

        if (($content[$following] ?? '') === ',') {
            $cutEnd = self::skipHorizontalWhitespace($content, $following + 1);
            if ($ownsItsLine && ($content[$cutEnd] ?? '') === Lines::LINE_BREAK) {
                $cutEnd++;
            }

            return substr($content, 0, $ownsItsLine ? $lineStart : $member->start()) . substr($content, $cutEnd);
        }

        // The last member takes the comma that precedes it, so the member before it stops being separated from nothing.
        return substr($content, 0, self::precedingNonWhitespace($content, $member->start())) . substr($content, $member->valueEnd());
    }

    /**
     * @param non-empty-list<string> $path
     * @param string|bool|int|non-empty-list<string> $value
     */
    private static function set(string $content, array $path, string|bool|int|array $value): string
    {
        [$object, $member, $depth] = self::walk($content, $path);
        $unit = self::detectIndent($content);

        if ($member instanceof JsonMember) {
            if ($member->valueIn($content) === $value) {
                return $content;
            }

            $rendered = self::renderValue($value, self::lineIndent($content, $member->start()), $unit);

            return substr($content, 0, $member->valueStart()) . $rendered . substr($content, $member->valueEnd());
        }

        $remaining = array_slice($path, $depth);
        // walk() stops short of the full path whenever it found no member, so there is always a key left to write; the guard states that rather than leaving it to a docblock.
        if ($remaining === []) {
            throw new RuntimeException(sprintf('"%s" resolved to no remaining key; it cannot be written.', implode('.', $path)));
        }

        $last = $object->last();

        // An object with nothing to preserve is filled in the canonical shape, one member per line.
        if (!$last instanceof JsonMember) {
            $closingIndent = self::lineIndent($content, $object->start());
            $memberIndent = $closingIndent . $unit;

            return substr($content, 0, $object->start() + 1)
                . Lines::LINE_BREAK . $memberIndent . self::renderMember($remaining, $value, $memberIndent, $unit)
                . Lines::LINE_BREAK . $closingIndent
                . substr($content, $object->end());
        }

        // An object written on its opening brace's line keeps that layout, so an addition never relayouts what the project wrote.
        if (self::lineStart($content, $last->start()) === self::lineStart($content, $object->start())) {
            return substr($content, 0, $last->valueEnd())
                . ', ' . self::renderMember($remaining, $value, null, $unit)
                . substr($content, $last->valueEnd());
        }

        $memberIndent = self::lineIndent($content, $last->start());

        return substr($content, 0, $last->valueEnd())
            . ',' . Lines::LINE_BREAK . $memberIndent . self::renderMember($remaining, $value, $memberIndent, $unit)
            . substr($content, $last->valueEnd());
    }

    /**
     * Walks the path from the root object, stopping at the member it names or at the deepest object that exists.
     *
     * @param non-empty-list<string> $path
     * @return array{JsonObject, ?JsonMember, int} the object the walk ended in, the member it named if written, and the depth reached
     */
    private static function walk(string $content, array $path): array
    {
        self::assertJsonObject($content);

        $object = self::scanObject($content, self::skipWhitespace($content, 0));
        foreach ($path as $depth => $key) {
            $member = $object->member($key);
            if (!$member instanceof JsonMember) {
                return [$object, null, $depth];
            }

            if ($depth === count($path) - 1) {
                return [$object, $member, $depth];
            }

            if ($content[$member->valueStart()] !== '{') {
                throw new RuntimeException(sprintf('"%s" does not hold an object; "%s" cannot be written beneath it.', implode('.', array_slice($path, 0, $depth + 1)), implode('.', $path)));
            }

            $object = self::scanObject($content, $member->valueStart());
        }

        return [$object, null, count($path)];
    }

    /**
     * Tokenizes one list's value spans in document order.
     *
     * @return list<array{int, int}>
     */
    private static function scanList(string $content, int $start): array
    {
        $spans = [];
        $index = self::skipWhitespace($content, $start + 1);
        while (($content[$index] ?? '') !== ']') {
            $valueStart = $index;
            $index = self::skipValue($content, $index);
            $spans[] = [$valueStart, $index];

            $index = self::skipWhitespace($content, $index);
            if (($content[$index] ?? '') === ',') {
                $index = self::skipWhitespace($content, $index + 1);
            }
        }

        return $spans;
    }

    /** Tokenizes one object's members in document order, keyed by their decoded name. */
    private static function scanObject(string $content, int $start): JsonObject
    {
        $members = [];
        $index = self::skipWhitespace($content, $start + 1);
        while (($content[$index] ?? '') !== '}') {
            $keyStart = $index;
            $index = self::skipValue($content, $index);
            $key = self::decode(substr($content, $keyStart, $index - $keyStart));

            $colon = self::skipWhitespace($content, $index);
            $valueStart = self::skipWhitespace($content, $colon + 1);
            $index = self::skipValue($content, $valueStart);

            if (isset($members[$key])) {
                throw new RuntimeException(sprintf('The JSON object sets "%s" more than once; the file cannot be managed.', $key));
            }
            $members[$key] = new JsonMember($keyStart, $valueStart, $index);

            $index = self::skipWhitespace($content, $index);
            if (($content[$index] ?? '') === ',') {
                $index = self::skipWhitespace($content, $index + 1);
            }
        }

        return new JsonObject($start, $index, $members);
    }

    /**
     * A null indent renders on one line, for a member being written into an object the project keeps on one line.
     *
     * @param string|bool|int|non-empty-list<string> $value
     */
    private static function renderValue(string|bool|int|array $value, ?string $indent, string $unit): string
    {
        if (!is_array($value)) {
            return self::encode($value);
        }

        if ($indent === null) {
            return '[' . implode(', ', array_map(static fn(string $entry): string => self::encode($entry), $value)) . ']';
        }

        $entries = array_map(static fn(string $entry): string => $indent . $unit . self::encode($entry), $value);

        return '[' . Lines::LINE_BREAK . implode(',' . Lines::LINE_BREAK, $entries) . Lines::LINE_BREAK . $indent . ']';
    }

    /**
     * One member, with the objects the remaining path still needs wrapped around it.
     *
     * @param non-empty-list<string> $path
     * @param string|bool|int|non-empty-list<string> $value
     */
    private static function renderMember(array $path, string|bool|int|array $value, ?string $indent, string $unit): string
    {
        $key = self::encode($path[0]);
        $beneath = array_slice($path, 1);
        if ($beneath === []) {
            return $key . ': ' . self::renderValue($value, $indent, $unit);
        }

        $inner = self::renderMember($beneath, $value, $indent === null ? null : $indent . $unit, $unit);
        if ($indent === null) {
            return $key . ': {' . $inner . '}';
        }

        return $key . ': {' . Lines::LINE_BREAK . $indent . $unit . $inner . Lines::LINE_BREAK . $indent . '}';
    }

    /** The file's own indentation unit, copied from its first indented member. */
    private static function detectIndent(string $content): string
    {
        return preg_match('/\n([ \t]+)"/', $content, $match) === 1 ? $match[1] : self::DEFAULT_INDENT;
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

    private static function skipValue(string $content, int $index): int
    {
        return match ($content[$index]) {
            '"' => self::skipString($content, $index),
            '{', '[' => self::skipBracketed($content, $index),
            default => self::skipLiteral($content, $index),
        };
    }

    /** The offset just past the string starting at $index; a closing quote must not be escaped. */
    private static function skipString(string $content, int $index): int
    {
        for ($cursor = $index + 1; $cursor < strlen($content); $cursor++) {
            if ($content[$cursor] === '\\') {
                $cursor++;
                continue;
            }
            if ($content[$cursor] === '"') {
                return $cursor + 1;
            }
        }

        throw new RuntimeException('A JSON string never closes; the file cannot be managed.');
    }

    /** The offset just past the object or array starting at $index; brackets inside strings do not count. */
    private static function skipBracketed(string $content, int $index): int
    {
        $depth = 0;
        for ($cursor = $index; $cursor < strlen($content); $cursor++) {
            $character = $content[$cursor];
            if ($character === '"') {
                $cursor = self::skipString($content, $cursor) - 1;
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

        throw new RuntimeException('A JSON object or array never closes; the file cannot be managed.');
    }

    private static function skipLiteral(string $content, int $index): int
    {
        return $index + strcspn($content, ',}]' . self::WHITESPACE, $index);
    }

    private static function skipWhitespace(string $content, int $index): int
    {
        return $index + strspn($content, self::WHITESPACE, $index);
    }

    private static function skipHorizontalWhitespace(string $content, int $index): int
    {
        return $index + strspn($content, " \t", $index);
    }

    private static function precedingNonWhitespace(string $content, int $index): int
    {
        $cursor = $index;
        while ($cursor > 0 && str_contains(self::WHITESPACE, $content[$cursor - 1])) {
            $cursor--;
        }

        return $cursor - 1;
    }

    private static function assertJsonObject(string $content): void
    {
        try {
            $decoded = json_decode($content, false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('The file is not valid JSON: %s', $exception->getMessage()), 0, $exception);
        }

        if (!$decoded instanceof stdClass) {
            throw new RuntimeException('The file does not hold a JSON object; it cannot be managed.');
        }
    }

    private static function encode(string|bool|int $value): string
    {
        return json_encode($value, self::RENDER_FLAGS);
    }

    private static function decode(string $json): mixed
    {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
