<?php

declare(strict_types=1);

namespace StandardsSync\Rules\Composer\Requirement;

use StandardsSync\Core\Rule\ExplainsDrift;
use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\Rule\Rule;
use StandardsSync\Formats\Json\JsonObjectWriter;
use StandardsSync\Rules\Composer\ComposerManifest;
use InvalidArgumentException;
use RuntimeException;

/**
 * Requires a package in the composer manifest exactly once, in a section that covers the declared need, at or above a minimum version.
 * A constraint reaching below the minimum is raised to it alternative-wise, so a project allowing a newer major keeps it; a package required where the declared need is not covered moves, carrying a constraint that already meets the minimum.
 * A root without a manifest is not a composer project, so the rule abstains rather than creating one.
 */
final readonly class ComposerRequirement implements Rule, ExplainsDrift
{
    public function __construct(
        private string $package,
        private VersionConstraint $constraint,
        private RequirementType $type = RequirementType::Development,
    ) {
        if ($constraint->lowestVersion() === null) {
            throw new InvalidArgumentException(sprintf('The constraint "%s" required for %s names a branch, so it states no minimum version to enforce.', $constraint->value(), $package));
        }
    }

    public function target(): FileTarget
    {
        return ComposerManifest::target();
    }

    public function apply(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $content = $this->dropTheRedundantRequirement($content);
        $section = $this->sectionsRequiring($content)[0] ?? null;
        $required = $section === null ? null : $this->requirementIn($content, $section);

        if ($section !== null && !$this->type->isMetBy($section)) {
            $content = JsonObjectWriter::remove($content, [$section->value, $this->package]);
            $section = null;
        }

        // Raising a constraint that already meets the minimum returns it unchanged, and writing an equal value leaves the file byte-identical, so one path serves adding, raising, moving and leaving alike.
        $constraint = $required?->raisedTo($this->constraint) ?? $this->constraint;

        return JsonObjectWriter::write($content, [($section ?? $this->type)->value, $this->package], $constraint->value());
    }

    public function description(): string
    {
        return sprintf('Requires %s in the composer manifest (%s), no lower than "%s".', $this->package, $this->type->value, $this->constraint->value());
    }

    public function explain(?string $content): string
    {
        $sections = $content === null ? [] : $this->sectionsRequiring($content);
        $section = $sections[0] ?? null;
        if ($section === null) {
            return sprintf('%s is not required; it is added to %s. Run "composer update %s" afterwards, or composer install will refuse the stale lock.', $this->package, $this->type->value, $this->package);
        }

        if (count($sections) > 1) {
            return sprintf('%s is required in both %s and %s, where both constraints apply at once; the %s entry is dropped.', $this->package, RequirementType::Runtime->value, RequirementType::Development->value, RequirementType::Development->value);
        }

        if (!$this->type->isMetBy($section)) {
            return sprintf('%s is required in %s, so it is not installed in production; it moves to %s.', $this->package, $section->value, $this->type->value);
        }

        $required = $this->requirementIn($content, $section) ?? $this->constraint;
        if ($required->lowestVersion() === null) {
            return sprintf('The required "%s" names a branch, which can resolve to any version and so meets no minimum; it is raised to "%s".', $required->value(), $this->constraint->value());
        }

        return sprintf('The required "%s" reaches below the "%s" minimum.', $required->value(), $this->constraint->value());
    }

    /** Both entries' constraints apply at once, so a package required in both sections carries a hidden extra minimum; the runtime entry is the one composer locks. */
    private function dropTheRedundantRequirement(string $content): string
    {
        if (count($this->sectionsRequiring($content)) < 2) {
            return $content;
        }

        return JsonObjectWriter::remove($content, [RequirementType::Development->value, $this->package]);
    }

    /** @return list<RequirementType> the sections requiring the package, runtime first */
    private function sectionsRequiring(string $content): array
    {
        return array_values(array_filter(
            RequirementType::cases(),
            fn (RequirementType $section): bool => $this->requirementIn($content, $section) !== null,
        ));
    }

    private function requirementIn(string $content, RequirementType $section): ?VersionConstraint
    {
        $written = JsonObjectWriter::read($content, [$section->value, $this->package]);
        if ($written === null) {
            return null;
        }

        try {
            return VersionConstraint::fromString($written);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(sprintf('The composer manifest requires %s at "%s" in %s, which is not a version constraint composer can parse.', $this->package, $written, $section->value), 0, $exception);
        }
    }
}
