<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\PhpStan\MinLevel;

use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\Text\Lines;
use AlleKnalle\StandardsSync\Formats\Neon\NeonScalarWriter;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanConfigFile;
use InvalidArgumentException;

/**
 * Keeps the PHPStan level at or above a minimum: a lower written level is raised, a stricter or equal one is never touched, and a missing level line — or the whole config — is written carrying the floor.
 * The rule reads only what the config itself writes, and a written line wins phpstan's include-merge, so the guarantee holds whatever an imported ruleset carries.
 * An optional org comment is enforced on the level line like the value itself — the line explains why it is managed; without one, a project's own trailing comment survives.
 */
final readonly class PhpStanMinLevel implements Rule, ExplainsDrift
{
    private const array LEVEL_PATH = ['parameters', 'level'];

    public function __construct(
        private PhpStanLevel $minLevel,
        private ?string $comment = null,
    ) {
        if ($this->comment !== null && ($this->comment === '' || str_contains($this->comment, Lines::LINE_BREAK))) {
            throw new InvalidArgumentException('A rule comment is one non-empty line.');
        }
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
        $written = $content === null ? null : NeonScalarWriter::read($content, self::LEVEL_PATH);

        // A compliant level keeps its own spelling ('max' stays 'max'); only the org comment is enforced on it.
        if ($written !== null && PhpStanLevel::fromConfigValue($written)->isAtLeast($this->minLevel)) {
            return $this->comment === null ? $content : NeonScalarWriter::ensureTrailingComment($content, self::LEVEL_PATH, $this->comment);
        }

        // Below the floor, no level line, or no config at all: write the floor — enforcing the standard is the point, and withoutRule() is the opt-out.
        return NeonScalarWriter::write($content ?? '', self::LEVEL_PATH, $this->minLevel->value(), $this->comment);
    }

    public function description(): string
    {
        return sprintf('Keeps the PHPStan level at or above %d.', $this->minLevel->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no PHPStan config yet; one is created with the minimum level %d.', $this->minLevel->value());
        }

        $written = NeonScalarWriter::read($content, self::LEVEL_PATH);
        if ($written === null) {
            return sprintf('No PHPStan level is written; %d is added as the minimum.', $this->minLevel->value());
        }

        $level = PhpStanLevel::fromConfigValue($written);
        if ($level->isAtLeast($this->minLevel)) {
            return 'The org comment on the level line is missing or altered.';
        }

        return sprintf('Level %d is below the minimum of %d.', $level->value(), $this->minLevel->value());
    }
}
