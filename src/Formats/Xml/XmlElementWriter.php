<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Formats\Xml;

use InvalidArgumentException;
use RuntimeException;

/**
 * Reads or sets one attribute on a named element's open tag in XML text.
 * Targeted edits only — everything around the touched attribute stays byte-identical; no parse, no reserialization.
 * The element scan skips comments, the open tag may span multiple lines, and anything unrecognizable fails loud rather than being half-edited.
 * Attribute values pass through raw: entities are neither decoded on read nor encoded on write, and a value that would need encoding is refused.
 */
final readonly class XmlElementWriter
{
    /** Inserted attributes use double quotes — the tools' own generated style; replacements keep the file's. */
    private const string INSERT_QUOTE = '"';

    /** The attribute's raw value as written on the element's open tag, or null when the attribute is absent. */
    public static function readAttribute(string $content, string $element, string $attribute): ?string
    {
        [, $attributesStart, $attributesEnd] = self::openTag($content, $element);
        $written = self::attributes($content, $element, $attributesStart, $attributesEnd);

        return $written[$attribute]['value'] ?? null;
    }

    /** Sets the attribute on the element's open tag: an existing value is replaced keeping its quotes, a missing attribute is appended after the last one, and an equal value leaves the content untouched. */
    public static function writeAttribute(string $content, string $element, string $attribute, string $value): string
    {
        [$tagStart, $attributesStart, $attributesEnd] = self::openTag($content, $element);
        $written = self::attributes($content, $element, $attributesStart, $attributesEnd);

        $existing = $written[$attribute] ?? null;
        if ($existing !== null) {
            self::assertNeedsNoEncoding($value, $existing['quote']);
            if ($existing['value'] === $value) {
                return $content;
            }

            return substr($content, 0, $existing['valueStart']) . $value . substr($content, $existing['valueEnd']);
        }

        self::assertNeedsNoEncoding($value, self::INSERT_QUOTE);
        $token = $attribute . '=' . self::INSERT_QUOTE . $value . self::INSERT_QUOTE;

        // An attribute-less tag gets the token right after the element name.
        if ($written === []) {
            return substr($content, 0, $attributesStart) . ' ' . $token . substr($content, $attributesStart);
        }

        $last = $written[array_key_last($written)];
        $lineStart = strrpos(substr($content, 0, $last['start']), "\n");

        // Inline after the last attribute when it shares a line with the element name; otherwise a fresh line copying that attribute's indentation.
        if ($lineStart === false || $lineStart < $tagStart) {
            return substr($content, 0, $last['end']) . ' ' . $token . substr($content, $last['end']);
        }

        preg_match('/^[ \t]*/', substr($content, $lineStart + 1), $indent);

        return substr($content, 0, $last['end']) . "\n" . $indent[0] . $token . substr($content, $last['end']);
    }

    /**
     * Locates the element's one open tag, skipping comments and requiring a name boundary so a longer element name never matches.
     * The end scan tracks quote state, so a ">" inside an attribute value does not close the tag.
     *
     * @return array{int, int, int} the tag's start, and the attribute span's start and end (the ">", or the "/" of a self-closing tag)
     */
    private static function openTag(string $content, string $element): array
    {
        $comments = self::commentRanges($content);
        $lead = '<' . $element;

        $tagStart = null;
        $offset = 0;
        while (($candidate = strpos($content, $lead, $offset)) !== false) {
            $offset = $candidate + 1;
            $boundary = $content[$candidate + strlen($lead)] ?? '';
            if (!in_array($boundary, [' ', "\t", "\n", "\r", '>', '/'], true)) {
                continue;
            }
            if (array_any($comments, static fn (array $range): bool => $candidate >= $range[0] && $candidate < $range[1])) {
                continue;
            }
            if ($tagStart !== null) {
                throw new RuntimeException(sprintf('More than one <%s> open tag found; the file cannot be managed.', $element));
            }
            $tagStart = $candidate;
        }

        if ($tagStart === null) {
            throw new RuntimeException(sprintf('No <%s> open tag found; the file cannot be managed.', $element));
        }

        $attributesStart = $tagStart + strlen($lead);
        $quote = null;
        for ($index = $attributesStart; $index < strlen($content); $index++) {
            $character = $content[$index];
            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }
            if ($character === '>') {
                $end = $content[$index - 1] === '/' ? $index - 1 : $index;

                return [$tagStart, $attributesStart, $end];
            }
        }

        throw new RuntimeException(sprintf('The <%s> open tag never closes; the file cannot be managed.', $element));
    }

    /**
     * Tokenizes the open tag's attribute span, in document order, failing loud on duplicates and on any content that is not a quoted attribute.
     *
     * @return array<string, array{start: int, end: int, quote: string, value: string, valueStart: int, valueEnd: int}> keyed by attribute name, offsets absolute in $content
     */
    private static function attributes(string $content, string $element, int $start, int $end): array
    {
        $span = substr($content, $start, $end - $start);
        preg_match_all('/([^\s=\'"\/>]+)\s*=\s*("[^"]*"|\'[^\']*\')/', $span, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $attributes = [];
        $covered = 0;
        foreach ($matches as $match) {
            [$token, $tokenOffset] = $match[0];
            self::assertOnlyWhitespace(substr($span, $covered, $tokenOffset - $covered), $element);
            $covered = $tokenOffset + strlen($token);

            $name = $match[1][0];
            if (isset($attributes[$name])) {
                throw new RuntimeException(sprintf('The <%s> open tag sets "%s" more than once; the file cannot be managed.', $element, $name));
            }

            $quoted = $match[2][0];
            $valueStart = $start + $match[2][1] + 1;
            $attributes[$name] = [
                'start' => $start + $tokenOffset,
                'end' => $start + $tokenOffset + strlen($token),
                'quote' => $quoted[0],
                'value' => substr($quoted, 1, -1),
                'valueStart' => $valueStart,
                'valueEnd' => $valueStart + strlen($quoted) - 2,
            ];
        }
        self::assertOnlyWhitespace(substr($span, $covered), $element);

        return $attributes;
    }

    /** @return list<array{int, int}> the comment spans, so the element scan can skip commented-out tags */
    private static function commentRanges(string $content): array
    {
        $ranges = [];
        $offset = 0;
        while (($open = strpos($content, '<!--', $offset)) !== false) {
            $close = strpos($content, '-->', $open + 4);
            if ($close === false) {
                throw new RuntimeException('An XML comment never closes; the file cannot be managed.');
            }
            $ranges[] = [$open, $close + 3];
            $offset = $close + 3;
        }

        return $ranges;
    }

    private static function assertOnlyWhitespace(string $gap, string $element): void
    {
        if (trim($gap) !== '') {
            throw new RuntimeException(sprintf('Unrecognized content "%s" in the <%s> open tag; the file cannot be managed.', trim($gap), $element));
        }
    }

    // Values are written raw, so anything XML would require entity-encoded is a caller error, refused loudly.
    private static function assertNeedsNoEncoding(string $value, string $quote): void
    {
        foreach (['<', '&', $quote] as $needsEncoding) {
            if (str_contains($value, $needsEncoding)) {
                throw new InvalidArgumentException(sprintf('The attribute value "%s" cannot be written raw: "%s" would need entity encoding.', $value, $needsEncoding));
            }
        }
    }
}
