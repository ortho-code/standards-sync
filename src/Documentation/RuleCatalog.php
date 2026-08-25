<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Documentation;

use AlleKnalle\StandardsSync\Core\Config\ConfigLoader;
use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\RuleSet\RuleSet;
use AlleKnalle\StandardsSync\Documentation\Model\CompositionSection;
use AlleKnalle\StandardsSync\Documentation\Model\DeclarationGroup;
use AlleKnalle\StandardsSync\Documentation\Model\ExampleKind;
use AlleKnalle\StandardsSync\Documentation\Model\FamilyPage;
use AlleKnalle\StandardsSync\Documentation\Model\FileExample;
use AlleKnalle\StandardsSync\Documentation\Model\RulePage;
use AlleKnalle\StandardsSync\Documentation\Model\ScenarioEntry;
use AlleKnalle\StandardsSync\Documentation\Model\Subsection;
use AlleKnalle\StandardsSync\Documentation\Source\ClassDescription;
use AlleKnalle\StandardsSync\Documentation\Source\RuleLibrary;
use AlleKnalle\StandardsSync\Documentation\Source\ScenarioTestSuite;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\DirectoryListing;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use AlleKnalle\StandardsSync\Testing\SyncFixtureTester;
use RuntimeException;

/**
 * The generated rule-catalog docs: a page per shipped rule, enumerated from the rule library so a rule
 * without scenario coverage refuses loudly, plus a slim page per family holding its cross-rule compositions
 * and one page for engine-level behaviours.
 * One source feeds tests and docs, so neither can rot alone.
 */
final readonly class RuleCatalog
{
    /** Where the generated pages live, relative to the project root. */
    public const string DIRECTORY = 'docs/rules';

    private const string INDEX_PAGE = 'README.md';

    /** The scenario suite and the namespace its test classes live under (see the suite layout in docs/conventions.md). */
    private const string SCENARIO_SUITE_DIRECTORY = 'tests/Scenario';
    private const string SCENARIO_NAMESPACE_PREFIX = 'Tests\\AlleKnalle\\StandardsSync\\Scenario';
    private const string SCENARIO_NAMESPACE_SEGMENT = 'Scenario';

    /** The tree's reserved segment for engine-level behaviours that are not any rule's own — not a family, so its scenarios get their own page. */
    private const string ENGINE_SEGMENT = 'Engine';

    /** The one directory beside a family's rules that no rule claims: its cross-rule compositions. */
    private const string FAMILY_COMPOSITION_DIRECTORY = 'Family';

    /** The rule library the scenario tree mirrors: a rule's namespace names the scenario directory holding its fixtures. */
    private const string RULE_LIBRARY_DIRECTORY = 'src/Rules';
    private const string RULE_LIBRARY_NAMESPACE = 'AlleKnalle\\StandardsSync\\Rules';

    private const string TEST_CLASS_SUFFIX = 'Test';

    /**
     * @param list<RulePage> $rulePages
     * @param list<FamilyPage> $familyPages
     * @param list<CompositionSection> $engineSections
     */
    private function __construct(private array $rulePages, private array $familyPages, private array $engineSections)
    {
    }

    public static function fromProject(string $projectRoot, Filesystem $filesystem = new SymfonyFilesystem()): self
    {
        $projectRoot = rtrim($projectRoot, '/');

        $suite = ScenarioTestSuite::fromDirectory(
            $projectRoot . '/' . self::SCENARIO_SUITE_DIRECTORY,
            self::SCENARIO_NAMESPACE_PREFIX,
        );
        $tree = [];
        foreach ($suite->testClasses() as $testClass) {
            [$family, $directory] = self::treeSegments($testClass);
            $tree[$family][$directory][] = $testClass;
        }
        ksort($tree);

        $library = RuleLibrary::fromDirectory(
            $projectRoot . '/' . self::RULE_LIBRARY_DIRECTORY,
            self::RULE_LIBRARY_NAMESPACE,
        );

        $rulePages = [];
        $claimed = [];
        foreach ($library->ruleClasses() as $ruleClass) {
            [$family, $ruleDirectory] = self::librarySegments($ruleClass);
            $testClasses = $tree[$family][$ruleDirectory] ?? [];
            if ($testClasses === []) {
                throw new RuntimeException(sprintf(
                    '%s has no scenario coverage under %s/%s/%s — every rule ships its before/after fixtures.',
                    $ruleClass,
                    self::SCENARIO_SUITE_DIRECTORY,
                    $family,
                    $ruleDirectory,
                ));
            }
            if (isset($claimed[$family][$ruleDirectory])) {
                throw new RuntimeException(sprintf(
                    '%s and %s share one scenario directory (%s/%s) — one rule folder holds one rule.',
                    $claimed[$family][$ruleDirectory],
                    $ruleClass,
                    $family,
                    $ruleDirectory,
                ));
            }
            $claimed[$family][$ruleDirectory] = $ruleClass;
            sort($testClasses);
            $rulePages[] = new RulePage(
                $family,
                self::shortName($ruleClass),
                ClassDescription::fromClass($ruleClass)?->text()
                    ?? throw new RuntimeException(sprintf('%s has no class docblock — the docblock is the rule\'s general description in the generated catalog.', $ruleClass)),
                self::subsections($testClasses, $projectRoot, $filesystem, $ruleClass),
            );
        }
        usort($rulePages, static fn (RulePage $a, RulePage $b): int => strcmp($a->name(), $b->name()));

        $familyPages = [];
        $engineSections = [];
        foreach ($tree as $family => $directories) {
            ksort($directories);
            $sections = [];
            foreach ($directories as $directory => $testClasses) {
                if (isset($claimed[$family][$directory])) {
                    continue;
                }
                // An orphaned directory would render as an unintended composition, so only the conventional shapes pass.
                if ($family !== self::ENGINE_SEGMENT && $directory !== self::FAMILY_COMPOSITION_DIRECTORY) {
                    throw new RuntimeException(sprintf(
                        '%s/%s/%s belongs to no rule — a rule\'s fixtures live in its mirrored directory, cross-rule compositions in "%s/".',
                        self::SCENARIO_SUITE_DIRECTORY,
                        $family,
                        $directory,
                        self::FAMILY_COMPOSITION_DIRECTORY,
                    ));
                }
                sort($testClasses);
                $sections[] = new CompositionSection(self::words($directory), self::subsections($testClasses, $projectRoot, $filesystem, ruleClass: null));
            }
            if ($sections === []) {
                continue;
            }
            if ($family === self::ENGINE_SEGMENT) {
                $engineSections = $sections;
                continue;
            }
            $ruleNames = array_map(
                static fn (RulePage $page): string => $page->name(),
                array_values(array_filter($rulePages, static fn (RulePage $page): bool => $page->family() === $family)),
            );
            $familyPages[] = new FamilyPage($family, $ruleNames, $sections);
        }

        return new self($rulePages, $familyPages, $engineSections);
    }

    /** @return array<string, string> page filename => markdown */
    public function pages(): array
    {
        $renderer = new MarkdownRenderer();

        $pages = [self::INDEX_PAGE => $renderer->renderIndex($this->rulePages, $this->familyPages, $this->engineSections)];
        foreach ($this->rulePages as $page) {
            $pages[$renderer->rulePageFilename($page->family(), $page->name())] = $renderer->renderRulePage($page);
        }
        foreach ($this->familyPages as $page) {
            $pages[$renderer->familyPageFilename($page->family())] = $renderer->renderFamilyPage($page);
        }
        if ($this->engineSections !== []) {
            $pages[$renderer->enginePageFilename()] = $renderer->renderEnginePage($this->engineSections);
        }

        return $pages;
    }

    /**
     * @param list<class-string<ScenarioTestCase>> $testClasses
     * @param ?class-string<Rule> $ruleClass the rule these scenarios document, or null for a composition
     * @return list<Subsection>
     */
    private static function subsections(array $testClasses, string $projectRoot, Filesystem $filesystem, ?string $ruleClass): array
    {
        $split = count($testClasses) > 1;
        $subsections = [];
        foreach ($testClasses as $testClass) {
            $fixtures = $testClass::fixturesDirectory();

            $entriesByConfig = [];
            foreach ($testClass::scenarios() as $heading => [$scenario, $config]) {
                $configPath = $fixtures . '/' . ($config ?? $scenario . '/' . SyncFixtureTester::CONFIG);
                $entriesByConfig[$configPath][] = new ScenarioEntry(
                    $heading,
                    substr($fixtures . '/' . $scenario, strlen($projectRoot) + 1),
                    self::examples($filesystem, $fixtures . '/' . $scenario),
                );
            }

            $groups = [];
            foreach ($entriesByConfig as $configPath => $entries) {
                $rules = self::rulesOf($configPath);
                self::assertDeclaresOnly($ruleClass, $rules, $configPath);
                $groups[] = new DeclarationGroup(
                    self::read($filesystem, $configPath),
                    array_map(static fn (Rule $rule): string => $rule->description(), $rules),
                    $entries,
                );
            }

            $subsections[] = new Subsection(
                $split ? self::words(self::shortName($testClass, trimSuffix: self::TEST_CLASS_SUFFIX)) : null,
                $groups,
            );
        }

        return $subsections;
    }

    /**
     * A rule's scenarios must declare that rule and nothing else; a foreign rule in the fixtures would render misleading docs.
     *
     * @param ?class-string<Rule> $ruleClass
     * @param list<Rule> $rules
     */
    private static function assertDeclaresOnly(?string $ruleClass, array $rules, string $configPath): void
    {
        if ($ruleClass === null) {
            return;
        }

        $foreign = array_find($rules, static fn (Rule $rule): bool => $rule::class !== $ruleClass);
        if ($foreign !== null) {
            throw new RuntimeException(sprintf(
                '"%s" declares %s inside the scenarios of %s — a rule\'s fixtures declare only that rule.',
                $configPath,
                $foreign::class,
                $ruleClass,
            ));
        }
    }

    /** @return list<Rule> */
    private static function rulesOf(string $configPath): array
    {
        $config = (new ConfigLoader())->loadFrom(Path::fromString($configPath));

        return array_merge(...array_map(static fn (RuleSet $ruleSet): array => $ruleSet->rules(), $config->ruleSets()));
    }

    /** @return list<FileExample> */
    private static function examples(Filesystem $filesystem, string $scenarioDirectory): array
    {
        $before = self::tree($filesystem, $scenarioDirectory . '/' . SyncFixtureTester::INPUT);
        $after = self::tree($filesystem, $scenarioDirectory . '/' . SyncFixtureTester::EXPECTED);

        $paths = array_unique([...array_keys($before), ...array_keys($after)]);
        sort($paths);

        return array_map(static fn (string $path): FileExample => new FileExample(
            $path,
            match (true) {
                !isset($before[$path]) => ExampleKind::Created,
                !isset($after[$path]) => ExampleKind::Removed,
                $before[$path] === $after[$path] => ExampleKind::Unchanged,
                default => ExampleKind::Changed,
            },
            $before[$path] ?? null,
            $after[$path] ?? null,
        ), $paths);
    }

    /** @return array<string, string> path relative to the tree root => content */
    private static function tree(Filesystem $filesystem, string $directory): array
    {
        $tree = [];
        foreach (DirectoryListing::fromDirectory($directory)->relativePaths() as $path) {
            $tree[$path] = self::read($filesystem, $directory . '/' . $path);
        }

        return $tree;
    }

    private static function read(Filesystem $filesystem, string $path): string
    {
        return $filesystem->read(Path::fromString($path))
            ?? throw new RuntimeException(sprintf('Cannot read "%s".', $path));
    }

    /**
     * The family and directory segments of a scenario test's namespace, e.g. PhpStan and MinLevel.
     *
     * @return array{string, string}
     */
    private static function treeSegments(string $testClass): array
    {
        $segments = explode('\\', $testClass);
        $at = array_search(self::SCENARIO_NAMESPACE_SEGMENT, $segments, true);
        if ($at === false || count($segments) !== $at + 4) {
            throw new RuntimeException(sprintf(
                'A scenario test class lives directly under a %s\\<Family>\\<Rule> namespace, got "%s".',
                self::SCENARIO_NAMESPACE_SEGMENT,
                $testClass,
            ));
        }

        return [$segments[$at + 1], $segments[$at + 2]];
    }

    /**
     * The family and rule segments of a rule class's namespace, naming the scenario directory that holds its fixtures.
     *
     * @return array{string, string}
     */
    private static function librarySegments(string $ruleClass): array
    {
        $prefix = self::RULE_LIBRARY_NAMESPACE . '\\';
        $segments = str_starts_with($ruleClass, $prefix)
            ? explode('\\', substr($ruleClass, strlen($prefix)))
            : [];
        if (count($segments) !== 3) {
            throw new RuntimeException(sprintf(
                'A shipped rule lives directly under %s\\<Family>\\<Rule>, got "%s".',
                self::RULE_LIBRARY_NAMESPACE,
                $ruleClass,
            ));
        }

        return [$segments[0], $segments[1]];
    }

    private static function shortName(string $class, string $trimSuffix = ''): string
    {
        $name = substr($class, (strrpos($class, '\\') ?: -1) + 1);

        return $trimSuffix !== '' && str_ends_with($name, $trimSuffix)
            ? substr($name, 0, -strlen($trimSuffix))
            : $name;
    }

    /** CamelCase to spaced words: "TargetResolution" reads as "Target Resolution" in a heading. */
    private static function words(string $camel): string
    {
        return (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $camel);
    }
}
