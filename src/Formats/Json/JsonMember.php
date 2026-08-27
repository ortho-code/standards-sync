<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Json;

/** One member of a JSON object: the spans its text and its value occupy in the document. */
final readonly class JsonMember
{
    public function __construct(
        private int $start,
        private int $valueStart,
        private int $valueEnd,
    ) {
    }

    /** The offset of the member's opening key quote; its text runs to the end of its value. */
    public function start(): int
    {
        return $this->start;
    }

    public function valueStart(): int
    {
        return $this->valueStart;
    }

    public function valueEnd(): int
    {
        return $this->valueEnd;
    }

    public function valueIn(string $content): mixed
    {
        return json_decode(substr($content, $this->valueStart, $this->valueEnd - $this->valueStart), true, flags: JSON_THROW_ON_ERROR);
    }
}
