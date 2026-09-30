<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

/**
 * A YAML document as lines, each keeping its own line ending, and the byte-order mark it opens with.
 * Joining the lines gives back the document byte for byte.
 */
final readonly class YamlLines
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    private const string LINE_FEED = "\n";

    private const string CARRIAGE_RETURN = "\r";

    /**
     * @param list<string> $texts each line without its ending
     * @param list<string> $endings each line's ending, empty for a last line without one
     */
    private function __construct(
        private string $byteOrderMark,
        private array $texts,
        private array $endings,
    ) {}

    public static function fromString(string $content): self
    {
        $byteOrderMark = str_starts_with($content, self::BYTE_ORDER_MARK) ? self::BYTE_ORDER_MARK : '';
        $rest = substr($content, strlen($byteOrderMark));
        $texts = [];
        $endings = [];
        $offset = 0;
        while ($offset < strlen($rest)) {
            $feed = strpos($rest, self::LINE_FEED, $offset);
            if ($feed === false) {
                $texts[] = substr($rest, $offset);
                $endings[] = '';
                break;
            }
            $line = substr($rest, $offset, $feed - $offset);
            $withReturn = str_ends_with($line, self::CARRIAGE_RETURN);
            $texts[] = $withReturn ? substr($line, 0, -1) : $line;
            $endings[] = ($withReturn ? self::CARRIAGE_RETURN : '') . self::LINE_FEED;
            $offset = $feed + 1;
        }

        return new self($byteOrderMark, $texts, $endings);
    }

    public function toString(): string
    {
        $content = $this->byteOrderMark;
        foreach ($this->texts as $index => $text) {
            $content .= $text . $this->endings[$index];
        }

        return $content;
    }

    public function count(): int
    {
        return count($this->texts);
    }

    /** The line's text without its ending; lines count from zero. */
    public function line(int $index): string
    {
        return $this->texts[$index];
    }

    /** Only a document's last line can end without a line break. */
    public function endsWithBreak(int $index): bool
    {
        return $this->endings[$index] !== '';
    }
}
