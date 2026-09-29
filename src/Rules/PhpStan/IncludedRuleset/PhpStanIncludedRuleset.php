<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Neon\NeonListWriter;
use OrthoCode\StandardsSync\Rules\PhpStan\PhpStanConfigFile;
use LogicException;

/**
 * Ensures the PHPStan config includes a given file, as a targeted edit that leaves the rest of the file untouched.
 * An existing block-form includes: section gains a missing entry in the place of one no standard declares any more, and after its last entry otherwise; a config without the section gains it at the top; a project without a PHPStan config gets one created, holding just the imports.
 * Declarations of included rulesets combine in declaration order, a ruleset declared twice counting once; an include declared at an earlier sync and declared by nobody now is retracted, and every other include is the project's and stays.
 */
final readonly class PhpStanIncludedRuleset implements Rule, ContributesToList, ExplainsDrift
{
    private const string SECTION = 'includes';

    /** @var non-empty-list<string> */
    private array $rulesets;

    /** @var list<string> */
    private array $retired;

    public function __construct(string $ruleset)
    {
        $this->rulesets = [Path::fromRelativeString($ruleset)->value()];
        $this->retired = [];
    }

    #[\Override]
    public function target(): FileTarget
    {
        return PhpStanConfigFile::target();
    }

    #[\Override]
    public function listKey(): string
    {
        return self::SECTION;
    }

    #[\Override]
    public function entries(): array
    {
        return $this->rulesets;
    }

    #[\Override]
    public function withMerged(ContributesToList $later): static
    {
        if (!$later instanceof self) {
            throw new LogicException('Only declarations of included PHPStan rulesets merge into one.');
        }

        /** @var static $merged psalm types clone-with as a plain object */
        $merged = clone($this, [
            'rulesets' => array_values(array_unique([...$this->rulesets, ...$later->rulesets])),
        ]);

        return $merged;
    }

    #[\Override]
    public function withRetired(array $retired): static
    {
        /** @var static $retiring psalm types clone-with as a plain object */
        $retiring = clone($this, [
            'retired' => $retired,
        ]);

        return $retiring;
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        // A project without a PHPStan config gets one: enforcing the standard is the point.
        $content ??= '';
        foreach ($this->rulesets as $ruleset) {
            $content = NeonListWriter::ensureEntry($content, self::SECTION, $ruleset, replacing: $this->retired);
        }

        return NeonListWriter::removeEntries($content, self::SECTION, $this->retired);
    }

    #[\Override]
    public function description(): string
    {
        return sprintf('Ensures the PHPStan config includes %s.', self::quoted($this->rulesets));
    }

    #[\Override]
    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no PHPStan config yet; one is created including %s.', self::quoted($this->rulesets));
        }

        $included = NeonListWriter::readList($content, self::SECTION) ?? [];

        $sentences = [];
        $missing = array_values(array_diff($this->rulesets, $included));
        if ($missing !== []) {
            $sentences[] = sprintf('The PHPStan config does not include %s.', self::quoted($missing));
        }

        $retracted = array_values(array_intersect($this->retired, $included));
        if ($retracted !== []) {
            $sentences[] = sprintf('It stops including %s, which no standard declares any more.', self::quoted($retracted));
        }

        return $sentences === [] ? 'The PHPStan includes differ from what the standards declare.' : implode(' ', $sentences);
    }

    /** @param list<string> $rulesets */
    private static function quoted(array $rulesets): string
    {
        return '"' . implode('", "', $rulesets) . '"';
    }
}
