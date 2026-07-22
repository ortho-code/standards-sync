<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\PhpStan;

use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\Text\Indent;
use AlleKnalle\StandardsSync\Core\Text\Lines;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ensures the PHPStan config includes a given file, as a targeted edit that leaves the rest of the file untouched.
 * An existing block-form includes: section gains the entry; a config without the section gains it at the top; a project without a PHPStan config gets one created, holding just the import.
 */
final readonly class PhpStanImportRule implements Rule, ExplainsDrift
{
    private const string SECTION = 'includes:';

    /** Neon's documented default indentation, used when the file has no indented line to copy. */
    private const string DEFAULT_INDENT = Indent::TAB;

    public function __construct(private string $import)
    {
        if (trim($this->import) === '') {
            throw new InvalidArgumentException('The import path cannot be empty.');
        }
    }

    public function target(): FileTarget
    {
        return PhpStanConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without a PHPStan config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        if ($content === null) {
            return $this->section(self::DEFAULT_INDENT);
        }

        $lines = Lines::split($content);
        $sectionIndex = $this->sectionIndex($lines);

        if ($sectionIndex === null) {
            return $this->section(Indent::detect($lines) ?? self::DEFAULT_INDENT) . Lines::LINE_BREAK . $content;
        }

        // Scan the section's entries: bail out when the import is already there, otherwise remember where the section ends.
        $lastEntryIndex = $sectionIndex;
        $entryIndent = null;
        for ($index = $sectionIndex + 1; $index < count($lines); $index++) {
            if (preg_match('/^([ \t]+)-[ \t]*(.*)$/', $lines[$index], $match) === 1) {
                if ($this->entryValue($match[2]) === $this->import) {
                    return $content;
                }
                $entryIndent ??= $match[1];
                $lastEntryIndex = $index;
                continue;
            }
            if (trim($lines[$index]) !== '') {
                break;
            }
        }

        array_splice($lines, $lastEntryIndex + 1, 0, [($entryIndent ?? Indent::detect($lines) ?? self::DEFAULT_INDENT) . '- ' . $this->import]);

        return Lines::join($lines);
    }

    public function description(): string
    {
        return sprintf('Ensures the PHPStan config includes "%s".', $this->import);
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no PHPStan config yet; one is created including "%s".', $this->import);
        }

        return sprintf('The PHPStan config does not include "%s".', $this->import);
    }

    private function section(string $indent): string
    {
        return self::SECTION . Lines::LINE_BREAK . $indent . '- ' . $this->import . Lines::LINE_BREAK;
    }

    /** @param list<string> $lines */
    private function sectionIndex(array $lines): ?int
    {
        $inlineForm = '/^' . preg_quote(self::SECTION, '/') . '[ \t]*\S/';
        if (array_any($lines, static fn (string $line): bool => preg_match($inlineForm, $line) === 1)) {
            throw new RuntimeException(sprintf('The "%s" section is not a block list; convert it to one "- entry" per line so the import can be managed.', self::SECTION));
        }

        return array_find_key($lines, static fn (string $line): bool => rtrim($line) === self::SECTION);
    }

    // Entries may quote their path; the import is matched on the unquoted value.
    private function entryValue(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^([\'"])(.*)\1$/', $value, $match) === 1) {
            return $match[2];
        }

        return $value;
    }
}
