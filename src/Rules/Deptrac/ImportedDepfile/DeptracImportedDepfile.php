<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Deptrac\ImportedDepfile;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Yaml\YamlListWriter;
use OrthoCode\StandardsSync\Rules\Deptrac\DeptracConfigFile;
use OrthoCode\StandardsSync\Rules\General\ListContribution\DeclaredEntries;
use LogicException;

/**
 * Ensures the deptrac config imports a given depfile, as a targeted edit that leaves the rest of the file untouched.
 * An existing block-form imports: section gains a missing entry in the place of one no standard declares any more, and after its last entry otherwise; a config without the section gains it at the top; a project without a deptrac config gets one created, holding just the imports.
 * Declarations of imported depfiles combine in declaration order, a depfile declared twice counting once; an import declared at an earlier sync and declared by nobody now is retracted, and every other import is the project's and stays.
 */
final readonly class DeptracImportedDepfile implements Rule, ContributesToList, ExplainsDrift
{
    private const string SECTION = 'imports';

    /** @var DeclaredEntries<string> */
    private DeclaredEntries $depfiles;

    public function __construct(string $depfile)
    {
        $this->depfiles = DeclaredEntries::fromString(Path::fromRelativeString($depfile)->value());
    }

    #[\Override]
    public function target(): FileTarget
    {
        return DeptracConfigFile::target();
    }

    #[\Override]
    public function listKey(): string
    {
        return self::SECTION;
    }

    #[\Override]
    public function entries(): array
    {
        return $this->depfiles->keys();
    }

    #[\Override]
    public function withMerged(ContributesToList $later): static
    {
        if (!$later instanceof self) {
            throw new LogicException('Only declarations of imported deptrac depfiles merge into one.');
        }

        /** @var static $merged psalm types clone-with as a plain object */
        $merged = clone($this, [
            'depfiles' => $this->depfiles->withMerged($later->depfiles),
        ]);

        return $merged;
    }

    #[\Override]
    public function withRetired(array $retired): static
    {
        /** @var static $retiring psalm types clone-with as a plain object */
        $retiring = clone($this, [
            'depfiles' => $this->depfiles->withRetired($retired),
        ]);

        return $retiring;
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        // A project without a deptrac config gets one: enforcing the standard is the point.
        $content ??= '';
        foreach ($this->depfiles->keys() as $depfile) {
            $content = YamlListWriter::ensureEntry($content, self::SECTION, $depfile, replacing: $this->depfiles->retired());
        }

        return YamlListWriter::removeEntries($content, self::SECTION, $this->depfiles->retired());
    }

    #[\Override]
    public function description(): string
    {
        return sprintf('Ensures the deptrac config imports %s.', self::quoted($this->depfiles->keys()));
    }

    #[\Override]
    public function explain(?string $content): string
    {
        if ($content === null) {
            return sprintf('There is no deptrac config yet; one is created importing %s.', self::quoted($this->depfiles->keys()));
        }

        $imported = YamlListWriter::readList($content, self::SECTION) ?? [];

        $sentences = [];
        $missing = $this->depfiles->missingFrom($imported);
        if ($missing !== []) {
            $sentences[] = sprintf('The deptrac config does not import %s.', self::quoted($missing));
        }

        $retracted = $this->depfiles->retractedFrom($imported);
        if ($retracted !== []) {
            $sentences[] = sprintf('It stops importing %s, which no standard declares any more.', self::quoted($retracted));
        }

        return $sentences === [] ? 'The deptrac imports differ from what the standards declare.' : implode(' ', $sentences);
    }

    /** @param list<string> $depfiles */
    private static function quoted(array $depfiles): string
    {
        return '"' . implode('", "', $depfiles) . '"';
    }
}
