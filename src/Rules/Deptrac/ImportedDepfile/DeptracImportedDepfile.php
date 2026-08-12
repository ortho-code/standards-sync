<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Deptrac\ImportedDepfile;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Formats\Yaml\YamlListWriter;
use AlleKnalle\StandardsSync\Rules\Deptrac\DeptracConfigFile;

/**
 * Ensures the deptrac config imports a given depfile, as a targeted edit that leaves the rest of the file untouched.
 * An existing block-form imports: section gains the entry; a config without the section gains it at the top; a project without a deptrac config gets one created, holding just the import.
 */
final readonly class DeptracImportedDepfile implements Rule, ExplainsDrift
{
    private const string SECTION = 'imports';

    private Path $depfile;

    public function __construct(string $depfile)
    {
        $this->depfile = Path::fromRelativeString($depfile);
    }

    public function target(): FileTarget
    {
        return DeptracConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        // A project without a deptrac config gets one: enforcing the standard is the point, and withoutRule() is the opt-out.
        return YamlListWriter::ensureEntry($content ?? '', self::SECTION, $this->depfile->value());
    }

    public function description(): string
    {
        return sprintf('Ensures the deptrac config imports "%s".', $this->depfile->value());
    }

    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no deptrac config yet; one is created importing "%s".', $this->depfile->value());
        }

        return sprintf('The deptrac config does not import "%s".', $this->depfile->value());
    }
}
