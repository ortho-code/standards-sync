<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Block;

/** Comment lead used to draw a managed block's markers, chosen per file type. */
enum MarkerSyntax
{
    case Hash;
    case DoubleSlash;
    case Semicolon;

    public function lead(): string
    {
        return match ($this) {
            self::Hash => '#',
            self::DoubleSlash => '//',
            self::Semicolon => ';',
        };
    }
}
