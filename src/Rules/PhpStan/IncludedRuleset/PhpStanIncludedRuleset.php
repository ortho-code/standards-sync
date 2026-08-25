<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpStan\IncludedRuleset;

use StandardsSync\Core\Filesystem\Path;
use StandardsSync\Core\Rule\ExplainsDrift;
use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\Rule\Rule;
use StandardsSync\Formats\Neon\NeonListWriter;
use StandardsSync\Rules\PhpStan\PhpStanConfigFile;

/**
 * Ensures the PHPStan config includes a given file, as a targeted edit that leaves the rest of the file untouched.
 * An existing block-form includes: section gains the entry; a config without the section gains it at the top; a project without a PHPStan config gets one created, holding just the import.
 */
final readonly class PhpStanIncludedRuleset implements Rule, ExplainsDrift
{
    private const string SECTION = 'includes';

    private Path $ruleset;

    public function __construct(string $ruleset)
    {
        $this->ruleset = Path::fromRelativeString($ruleset);
    }

    public function target(): FileTarget
    {
        return PhpStanConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without a PHPStan config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        return NeonListWriter::ensureEntry($content ?? '', self::SECTION, $this->ruleset->value());
    }

    public function description(): string
    {
        return sprintf('Ensures the PHPStan config includes "%s".', $this->ruleset->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no PHPStan config yet; one is created including "%s".', $this->ruleset->value());
        }

        return sprintf('The PHPStan config does not include "%s".', $this->ruleset->value());
    }
}
