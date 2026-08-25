<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Documentation\Source;

use AlleKnalle\StandardsSync\Core\Text\Lines;
use ReflectionClass;

/** A class's docblock as plain prose: delimiters and line leads stripped, tag lines dropped, blank lines kept as paragraph breaks. */
final readonly class ClassDescription
{
    private const string OPENER = '/**';
    private const string CLOSER = '*/';
    private const string LINE_LEAD = '*';
    private const string TAG_LEAD = '@';

    private const string PARAGRAPH_SEPARATOR = Lines::LINE_BREAK . Lines::LINE_BREAK;

    private function __construct(private string $text)
    {
    }

    public static function fromClass(string $class): ?self
    {
        $docblock = (new ReflectionClass($class))->getDocComment();
        if ($docblock === false) {
            return null;
        }

        $paragraphs = [];
        $current = [];
        foreach (Lines::split($docblock) as $line) {
            $line = trim($line);
            if (str_starts_with($line, self::OPENER)) {
                $line = trim(substr($line, strlen(self::OPENER)));
            }
            if (str_ends_with($line, self::CLOSER)) {
                $line = trim(substr($line, 0, -strlen(self::CLOSER)));
            }
            if (str_starts_with($line, self::LINE_LEAD)) {
                $line = trim(substr($line, strlen(self::LINE_LEAD)));
            }
            if (str_starts_with($line, self::TAG_LEAD)) {
                continue;
            }
            if ($line === '') {
                if ($current !== []) {
                    $paragraphs[] = implode(' ', $current);
                    $current = [];
                }
                continue;
            }
            $current[] = $line;
        }
        if ($current !== []) {
            $paragraphs[] = implode(' ', $current);
        }

        return $paragraphs === [] ? null : new self(implode(self::PARAGRAPH_SEPARATOR, $paragraphs));
    }

    public function text(): string
    {
        return $this->text;
    }
}
