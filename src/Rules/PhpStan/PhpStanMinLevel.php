<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\PhpStan;

use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\OnMissing;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\Text\Indent;
use AlleKnalle\StandardsSync\Core\Text\Lines;

/**
 * Keeps the PHPStan level at or above a minimum: a lower written level is raised, a stricter or equal one is never touched.
 * The rule reads only what the config itself writes; a config without a level line follows the OnMissing choice.
 * Skip (the default) leaves it to whatever owns the effective level — typically the imported shared ruleset carrying the floor; Write writes the floor for setups where nothing else supplies a level.
 */
final readonly class PhpStanMinLevel implements Rule, ExplainsDrift
{
    private const string SECTION = 'parameters:';

    /** Neon's documented default indentation, used when the file has no indented line to copy. */
    private const string DEFAULT_INDENT = Indent::TAB;

    public function __construct(
        private PhpStanLevel $minLevel,
        private OnMissing $onMissing = OnMissing::Skip,
    ) {
    }

    public function minLevel(): PhpStanLevel
    {
        return $this->minLevel;
    }

    public function target(): FileTarget
    {
        return PhpStanConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // No PHPStan config, no opinion: creating one is the import rule's job.
        if ($content === null) {
            return null;
        }

        $lines = Lines::split($content);
        $levelIndex = $this->levelLineIndex($lines);

        if ($levelIndex === null) {
            return $this->onMissing === OnMissing::Write ? $this->withWrittenFloor($content, $lines) : $content;
        }

        if ($this->writtenLevel($lines[$levelIndex])->isAtLeast($this->minLevel)) {
            return $content;
        }

        $lines[$levelIndex] = (string) preg_replace('/^([ \t]+level:[ \t]*)\S+/', '${1}' . $this->minLevel->value(), $lines[$levelIndex]);

        return Lines::join($lines);
    }

    public function description(): string
    {
        return sprintf('Keeps the PHPStan level at or above %d.', $this->minLevel->value());
    }

    public function explain(?string $content): string
    {
        if ($content !== null) {
            $lines = Lines::split($content);
            $levelIndex = $this->levelLineIndex($lines);
            if ($levelIndex !== null) {
                return sprintf('Level %d is below the minimum of %d.', $this->writtenLevel($lines[$levelIndex])->value(), $this->minLevel->value());
            }

            return sprintf('No PHPStan level is written; %d is added as the minimum.', $this->minLevel->value());
        }

        return sprintf('The PHPStan level is below the minimum of %d.', $this->minLevel->value());
    }

    /**
     * Writes the floor as the level: as the first child of an existing parameters: section, or in a new section at the end.
     *
     * @param list<string> $lines
     */
    private function withWrittenFloor(string $content, array $lines): string
    {
        $levelLine = (Indent::detect($lines) ?? self::DEFAULT_INDENT) . 'level: ' . $this->minLevel->value();

        $sectionIndex = array_find_key($lines, static fn (string $line): bool => rtrim($line) === self::SECTION);
        if ($sectionIndex === null) {
            return rtrim($content, Lines::LINE_BREAK) . Lines::LINE_BREAK . Lines::LINE_BREAK . self::SECTION . Lines::LINE_BREAK . $levelLine . Lines::LINE_BREAK;
        }

        array_splice($lines, $sectionIndex + 1, 0, [$levelLine]);

        return Lines::join($lines);
    }

    /**
     * The level line directly under parameters:, matched on the section's own child indentation so a nested level key (e.g. an extension's) is never touched.
     *
     * @param list<string> $lines
     */
    private function levelLineIndex(array $lines): ?int
    {
        $inParameters = false;
        $childIndent = null;
        foreach ($lines as $index => $line) {
            if (rtrim($line) === self::SECTION) {
                $inParameters = true;
                $childIndent = null;
                continue;
            }
            if (!$inParameters) {
                continue;
            }
            if (preg_match('/^\S/', $line) === 1) {
                $inParameters = false;
                continue;
            }
            if (preg_match('/^([ \t]+)\S/', $line, $match) === 1) {
                $childIndent ??= $match[1];
                if ($match[1] === $childIndent && preg_match('/^[ \t]+level:[ \t]*\S/', $line) === 1) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function writtenLevel(string $line): PhpStanLevel
    {
        preg_match('/^[ \t]+level:[ \t]*(\S+)/', $line, $match);

        return PhpStanLevel::fromConfigValue($match[1]);
    }
}
