<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation;

use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Documentation\Model\CompositionSection;
use OrthoCode\StandardsSync\Documentation\Model\ExampleKind;
use OrthoCode\StandardsSync\Documentation\Model\FamilyPage;
use OrthoCode\StandardsSync\Documentation\Model\FileExample;
use OrthoCode\StandardsSync\Documentation\Model\RulePage;
use OrthoCode\StandardsSync\Documentation\Model\Subsection;

/** Renders the catalog model as markdown pages: deterministic output, fenced file contents, links back into the fixture tree. */
final readonly class MarkdownRenderer
{
    private const string GENERATED_NOTICE = '<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->';
    private const string INDEX_TITLE = 'Rule catalog';
    private const string PAGE_EXTENSION = '.md';

    /** Markdown blocks are separated by one blank line. */
    private const string BLOCK_SEPARATOR = Lines::LINE_BREAK . Lines::LINE_BREAK;

    /** Heading levels: content directly under a page title, and content under a section heading. */
    private const int BELOW_TITLE_LEVEL = 2;
    private const int BELOW_SECTION_LEVEL = 3;

    /** Top-level PHP preamble lines dropped from a rendered declaration; the rules and their comments stay verbatim. */
    private const array PREAMBLE_PATTERNS = ['/^<\?php\b/', '/^declare\(/', '/^use [A-Za-z\\\\]/'];

    private const string PHP_LANGUAGE = 'php';
    private const array FENCE_LANGUAGES = [
        'neon' => 'neon',
        'php' => 'php',
        'xml' => 'xml',
        'json' => 'json',
        'json5' => 'json5',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'editorconfig' => 'ini',
    ];

    /** A fence must outrun the longest backtick run inside it, and markdown's minimum is three. */
    private const string FENCE_CHARACTER = '`';
    private const int FENCE_MINIMUM = 3;

    /** The dotted segment naming a dist variant; the fence language comes from the extension underneath it. */
    private const string DIST_EXTENSION = 'dist';

    /** The page that renders when a family folder is browsed. */
    private const string FAMILY_PAGE = 'README.md';

    /** The engine-behaviours page: not a family, so it lives at the catalog root beside the index. */
    private const string ENGINE_PAGE = 'engine';
    private const string ENGINE_TITLE = 'Engine behaviours';

    public function rulePageFilename(string $family, string $ruleName): string
    {
        return strtolower($family) . '/' . $ruleName . self::PAGE_EXTENSION;
    }

    public function familyPageFilename(string $family): string
    {
        return strtolower($family) . '/' . self::FAMILY_PAGE;
    }

    public function enginePageFilename(): string
    {
        return self::ENGINE_PAGE . self::PAGE_EXTENSION;
    }

    /**
     * @param list<RulePage> $rulePages
     * @param list<FamilyPage> $familyPages
     * @param list<CompositionSection> $engineSections
     */
    public function renderIndex(array $rulePages, array $familyPages, array $engineSections): string
    {
        $ruleNamesByFamily = [];
        foreach ($rulePages as $page) {
            $ruleNamesByFamily[$page->family()][] = $page->name();
        }
        $pagedFamilies = array_map(static fn(FamilyPage $page): string => $page->family(), $familyPages);

        $families = array_unique([...array_keys($ruleNamesByFamily), ...$pagedFamilies]);
        sort($families);

        $lines = [];
        foreach ($families as $family) {
            $lines[] = in_array($family, $pagedFamilies, true)
                ? sprintf('- [%s](%s)', $family, $this->familyPageFilename($family))
                : sprintf('- **%s**', $family);
            foreach ($ruleNamesByFamily[$family] ?? [] as $rule) {
                $lines[] = sprintf('  - [%s](%s)', $rule, $this->rulePageFilename($family, $rule));
            }
        }

        $blocks = [
            self::GENERATED_NOTICE,
            '# ' . self::INDEX_TITLE,
            'Every example is generated from the scenario suite (`tests/Scenario`): the fixtures are the engine\'s behaviour catalog, so these pages cannot drift from what the tests pin. Each rule has its own page; a linked family page holds its cross-rule compositions, and engine-level behaviours live on their own page.',
            Lines::join($lines),
        ];
        if ($engineSections !== []) {
            $blocks[] = 'Engine behaviours, not any rule\'s own: ' . implode(', ', array_map(
                fn(CompositionSection $section): string => sprintf('[%s](%s)', $section->name(), $this->enginePageFilename()),
                $engineSections,
            ));
        }

        return $this->page($blocks);
    }

    public function renderRulePage(RulePage $page): string
    {
        $blocks = [self::GENERATED_NOTICE, '# ' . $page->name(), $page->description()];
        array_push($blocks, ...$this->subsectionBlocks($page->subsections(), self::BELOW_TITLE_LEVEL, $this->linkPrefix(1)));

        return $this->page($blocks);
    }

    public function renderFamilyPage(FamilyPage $page): string
    {
        $blocks = [self::GENERATED_NOTICE, '# ' . $page->family()];
        if ($page->ruleNames() !== []) {
            // The rule pages share the family page's folder, so the links are plain basenames.
            $blocks[] = 'Rules: ' . implode(' · ', array_map(
                static fn(string $rule): string => sprintf('[%s](%s)', $rule, $rule . self::PAGE_EXTENSION),
                $page->ruleNames(),
            ));
        }
        foreach ($page->sections() as $section) {
            $blocks[] = $this->heading(self::BELOW_TITLE_LEVEL, $section->name());
            array_push($blocks, ...$this->subsectionBlocks($section->subsections(), self::BELOW_SECTION_LEVEL, $this->linkPrefix(1)));
        }

        return $this->page($blocks);
    }

    /** @param list<CompositionSection> $sections */
    public function renderEnginePage(array $sections): string
    {
        $blocks = [self::GENERATED_NOTICE, '# ' . self::ENGINE_TITLE];
        foreach ($sections as $section) {
            $blocks[] = $this->heading(self::BELOW_TITLE_LEVEL, $section->name());
            array_push($blocks, ...$this->subsectionBlocks($section->subsections(), self::BELOW_SECTION_LEVEL, $this->linkPrefix(0)));
        }

        return $this->page($blocks);
    }

    /**
     * A finished page: its blocks blank-line separated, the file newline-terminated.
     *
     * @param list<string> $blocks
     */
    private function page(array $blocks): string
    {
        return implode(self::BLOCK_SEPARATOR, $blocks) . Lines::LINE_BREAK;
    }

    /**
     * @param list<Subsection> $subsections
     * @return list<string>
     */
    private function subsectionBlocks(array $subsections, int $level, string $linkPrefix): array
    {
        $blocks = [];
        foreach ($subsections as $subsection) {
            $titled = $subsection->title() !== null;
            if ($titled) {
                $blocks[] = $this->heading($level, $subsection->title());
            }
            foreach ($subsection->groups() as $group) {
                $blocks[] = 'Declared as:';
                $blocks[] = $this->fence(self::PHP_LANGUAGE, $this->declarationListing($group->configSource()));
                $blocks[] = $this->reports($group->reportLines());
                foreach ($group->entries() as $entry) {
                    $blocks[] = $this->heading($titled ? $level + 1 : $level, ucfirst($entry->heading()));
                    $blocks[] = sprintf('Fixture: [`%s`](%s%s)', $entry->fixtureDirectory(), $linkPrefix, $entry->fixtureDirectory());
                    array_push($blocks, ...$this->examples($entry->examples()));
                }
            }
        }

        return $blocks;
    }

    /**
     * @param list<FileExample> $examples
     * @return list<string>
     */
    private function examples(array $examples): array
    {
        $blocks = [];
        foreach ($examples as $example) {
            $path = self::FENCE_CHARACTER . $example->path() . self::FENCE_CHARACTER;
            array_push($blocks, ...match ($example->kind()) {
                ExampleKind::Changed => [
                    sprintf('**Before** — %s:', $path),
                    $this->fenceFor($example->path(), (string) $example->before()),
                    '**After:**',
                    $this->fenceFor($example->path(), (string) $example->after()),
                ],
                ExampleKind::Created => [
                    sprintf('**Creates** %s:', $path),
                    $this->fenceFor($example->path(), (string) $example->after()),
                ],
                ExampleKind::Unchanged => [
                    sprintf('%s **stays byte-identical**:', $path),
                    $this->fenceFor($example->path(), (string) $example->after()),
                ],
                ExampleKind::Removed => [
                    sprintf('**Removes** %s, previously:', $path),
                    $this->fenceFor($example->path(), (string) $example->before()),
                ],
            });
        }

        return $blocks;
    }

    /** @param list<string> $lines */
    private function reports(array $lines): string
    {
        if (count($lines) === 1) {
            return sprintf('…which reports as: *%s*', $lines[0]);
        }

        return '…which report as:' . self::BLOCK_SEPARATOR . Lines::join(array_map(
            static fn(string $line): string => sprintf('- *%s*', $line),
            $lines,
        ));
    }

    /** The declaration as a reader-facing listing: the PHP preamble drops, everything else — comments included — stays verbatim. */
    private function declarationListing(string $source): string
    {
        $kept = array_filter(
            Lines::split($source),
            static fn(string $line): bool => !array_any(
                self::PREAMBLE_PATTERNS,
                static fn(string $pattern): bool => preg_match($pattern, $line) === 1,
            ),
        );

        $listing = (string) preg_replace('/\n{3,}/', self::BLOCK_SEPARATOR, Lines::join(array_values($kept)));

        return ltrim($listing, Lines::LINE_BREAK);
    }

    private function heading(int $level, string $text): string
    {
        return str_repeat('#', $level) . ' ' . $text;
    }

    private function fenceFor(string $path, string $content): string
    {
        return $this->fence($this->language($path), $content);
    }

    private function language(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === self::DIST_EXTENSION) {
            $extension = pathinfo(substr($path, 0, -(strlen(self::DIST_EXTENSION) + 1)), PATHINFO_EXTENSION);
        }

        return self::FENCE_LANGUAGES[$extension] ?? '';
    }

    private function fence(string $language, string $content): string
    {
        preg_match_all('/' . self::FENCE_CHARACTER . '+/', $content, $matches);
        $longest = max([0, ...array_map(strlen(...), $matches[0])]);
        $fence = str_repeat(self::FENCE_CHARACTER, max(self::FENCE_MINIMUM, $longest + 1));
        $body = str_ends_with($content, Lines::LINE_BREAK) ? $content : $content . Lines::LINE_BREAK;

        return $fence . $language . Lines::LINE_BREAK . $body . $fence;
    }

    /** The way back to the project root for fixture links, from a page the given number of folders below the catalog directory. */
    private function linkPrefix(int $foldersBelowCatalog): string
    {
        return str_repeat('../', substr_count(RuleCatalog::DIRECTORY, '/') + 1 + $foldersBelowCatalog);
    }
}
