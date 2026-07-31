<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\General\ManagedBlock;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use InvalidArgumentException;

/** Comment lead used to draw a managed block's markers, chosen per file type. */
enum MarkerSyntax
{
    case Hash;
    case DoubleSlash;
    case Semicolon;

    /** Comment lead per file extension; anything unlisted uses a hash comment. */
    private const array BY_EXTENSION = [
        'js' => self::DoubleSlash,
        'mjs' => self::DoubleSlash,
        'cjs' => self::DoubleSlash,
        'ts' => self::DoubleSlash,
        'ini' => self::Semicolon,
    ];

    /** Formats with no line-comment syntax at all; a managed block cannot target these. */
    private const array WITHOUT_LINE_COMMENTS = ['json', 'xml', 'html'];

    /**
     * The comment syntax is a fact about the file type, so it is derived from the path rather than declared by the config author.
     * Extensions are walked right to left so distribution suffixes (phpunit.xml.dist) do not hide the real type; formats without line comments are refused.
     */
    public static function fromPath(Path $path): self
    {
        $segments = explode('.', strtolower(basename($path->value())));
        foreach (array_reverse(array_slice($segments, 1)) as $extension) {
            if (in_array($extension, self::WITHOUT_LINE_COMMENTS, true)) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" cannot carry a managed marker block: %s has no line comments; use a structured rule.',
                    $path->value(),
                    $extension,
                ));
            }
            if (isset(self::BY_EXTENSION[$extension])) {
                return self::BY_EXTENSION[$extension];
            }
        }

        return self::Hash;
    }

    public function lead(): string
    {
        return match ($this) {
            self::Hash => '#',
            self::DoubleSlash => '//',
            self::Semicolon => ';',
        };
    }
}
