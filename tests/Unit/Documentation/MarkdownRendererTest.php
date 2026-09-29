<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Documentation;

use OrthoCode\StandardsSync\Documentation\MarkdownRenderer;
use OrthoCode\StandardsSync\Documentation\Model\CompositionSection;
use OrthoCode\StandardsSync\Documentation\Model\DeclarationGroup;
use OrthoCode\StandardsSync\Documentation\Model\ExampleKind;
use OrthoCode\StandardsSync\Documentation\Model\FamilyPage;
use OrthoCode\StandardsSync\Documentation\Model\FileExample;
use OrthoCode\StandardsSync\Documentation\Model\RulePage;
use OrthoCode\StandardsSync\Documentation\Model\ScenarioEntry;
use OrthoCode\StandardsSync\Documentation\Model\Subsection;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownRenderer::class)]
final class MarkdownRendererTest extends TestCase
{
    public function testRendersARulePageWithTheNoticeDescriptionDeclarationReportAndExamples(): void
    {
        $configSource = FileContent::fromString(<<<'PHP'
            <?php

            declare(strict_types=1);

            use OrthoCode\StandardsSync\Core\Config\SyncConfig;

            // A comment kept.
            return config();
            PHP);

        $page = new RulePage('PhpStan', 'PhpStanMinLevel', 'Keeps the level.', [
            new Subsection(null, [
                new DeclarationGroup($configSource, ['Keeps the PHPStan level at or above 7.'], [
                    new ScenarioEntry('a lower level is raised', 'tests/Scenario/PhpStan/MinLevel/fixtures/raise', [
                        new FileExample(
                            'phpstan.neon',
                            ExampleKind::Changed,
                            FileContent::fromString(<<<'NEON'
                                parameters:
                                	level: 4
                                NEON),
                            FileContent::fromString(<<<'NEON'
                                parameters:
                                	level: 7
                                NEON),
                        ),
                    ]),
                ]),
            ]),
        ]);

        self::assertSame(FileContent::fromString(<<<'MD'
            <!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

            # PhpStanMinLevel

            Keeps the level.

            Declared as:

            ```php
            // A comment kept.
            return config();
            ```

            …which reports as: *Keeps the PHPStan level at or above 7.*

            ## A lower level is raised

            Fixture: [`tests/Scenario/PhpStan/MinLevel/fixtures/raise`](../../../tests/Scenario/PhpStan/MinLevel/fixtures/raise)

            **Before** — `phpstan.neon`:

            ```neon
            parameters:
            	level: 4
            ```

            **After:**

            ```neon
            parameters:
            	level: 7
            ```
            MD), (new MarkdownRenderer())->renderRulePage($page));
    }

    public function testAFenceOutrunsBackticksInTheContentAndADistNameKeepsItsUnderlyingLanguage(): void
    {
        $page = new RulePage('General', 'ManagedBlock', 'Places a template as a managed block.', [
            new Subsection(null, [
                new DeclarationGroup(FileContent::fromString('return config();'), ['Places a block.'], [
                    new ScenarioEntry('a fenced template', 'tests/Scenario/General/f', [
                        new FileExample(
                            'README.md',
                            ExampleKind::Created,
                            null,
                            FileContent::fromString(<<<'MD'
                                ```
                                inside
                                ```
                                MD),
                        ),
                        new FileExample('phpstan.neon.dist', ExampleKind::Created, null, FileContent::fromString('parameters:')),
                    ]),
                ]),
            ]),
        ]);

        $rendered = (new MarkdownRenderer())->renderRulePage($page);

        // Substrings of the rendered page rather than file fixtures, so the escapes mark where each line of a fence ends.
        self::assertStringContainsString("````\n```\ninside\n```\n````", $rendered);
        self::assertStringContainsString("```neon\nparameters:\n```", $rendered);
    }

    public function testMultipleRulesReportAsABulletListAndTitledSubsectionsNestTheirScenarios(): void
    {
        $page = new RulePage('General', 'ManagedBlock', 'Places a template as a managed block.', [
            new Subsection('First Sync', [
                new DeclarationGroup(FileContent::fromString('return config();'), ['Places the "ci" block.', 'Places the "framework" block.'], [
                    new ScenarioEntry('two blocks land', 'tests/Scenario/General/f', [
                        new FileExample('.gitignore', ExampleKind::Unchanged, FileContent::fromString('kept'), FileContent::fromString('kept')),
                    ]),
                ]),
            ]),
        ]);

        $rendered = (new MarkdownRenderer())->renderRulePage($page);

        // Substrings of the rendered page rather than file fixtures, and the blank lines around a heading are part of what each one pins.
        self::assertStringContainsString("…which report as:\n\n- *Places the \"ci\" block.*\n- *Places the \"framework\" block.*", $rendered);
        self::assertStringContainsString("\n\n## First Sync\n\n", $rendered);
        self::assertStringContainsString("\n\n### Two blocks land\n\n", $rendered);
        self::assertStringContainsString('`.gitignore` **stays byte-identical**:', $rendered);
    }

    public function testAFamilyPageLinksItsRulesAndSectionsItsCompositions(): void
    {
        $page = new FamilyPage('PhpStan', ['PhpStanIncludedRuleset', 'PhpStanMinLevel'], [
            new CompositionSection('Family', [
                new Subsection(null, [
                    new DeclarationGroup(FileContent::fromString('return config();'), ['Includes the ruleset.', 'Keeps the level.'], [
                        new ScenarioEntry('both rules fold together', 'tests/Scenario/PhpStan/Family/fixtures/from-scratch', [
                            new FileExample('phpstan.neon', ExampleKind::Created, null, FileContent::fromString('parameters:')),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $rendered = (new MarkdownRenderer())->renderFamilyPage($page);

        // Substrings of the rendered page rather than file fixtures, and the blank lines around a heading are part of what each one pins.
        self::assertStringContainsString("# PhpStan\n\nRules: [PhpStanIncludedRuleset](PhpStanIncludedRuleset.md) · [PhpStanMinLevel](PhpStanMinLevel.md)", $rendered);
        self::assertStringContainsString("\n\n## Family\n\n", $rendered);
        self::assertStringContainsString("\n\n### Both rules fold together\n\n", $rendered);
    }

    public function testTheIndexGroupsRulesByFamilyLinkingFamilyPagesAndTheEnginePage(): void
    {
        $rulePages = [
            new RulePage('PhpStan', 'PhpStanMinLevel', 'Keeps the level.', []),
            new RulePage('General', 'ManagedBlock', 'Places a block.', []),
        ];
        $familyPages = [
            new FamilyPage('PhpStan', ['PhpStanMinLevel'], []),
        ];
        $engineSections = [
            new CompositionSection('Target Resolution', []),
        ];

        self::assertSame(FileContent::fromString(<<<'MD'
            <!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

            # Rule catalog

            Every example is generated from the scenario suite (`tests/Scenario`): the fixtures are the engine's behaviour catalog, so these pages cannot drift from what the tests pin. Each rule has its own page; a linked family page holds its cross-rule compositions, and engine-level behaviours live on their own page.

            - **General**
              - [ManagedBlock](general/ManagedBlock.md)
            - [PhpStan](phpstan/README.md)
              - [PhpStanMinLevel](phpstan/PhpStanMinLevel.md)

            Engine behaviours, not any rule's own: [Target Resolution](engine.md)
            MD), (new MarkdownRenderer())->renderIndex($rulePages, $familyPages, $engineSections));
    }

    public function testTheEnginePageSitsAtTheCatalogRootAndLinksFixturesFromThere(): void
    {
        $sections = [
            new CompositionSection('Target Resolution', [
                new Subsection(null, [
                    new DeclarationGroup(FileContent::fromString('return config();'), ['Keeps the level.'], [
                        new ScenarioEntry('a lone dist file is synced', 'tests/Scenario/Engine/TargetResolution/fixtures/lone-dist-file', [
                            new FileExample('phpstan.neon.dist', ExampleKind::Created, null, FileContent::fromString('parameters:')),
                        ]),
                    ]),
                ]),
            ]),
        ];

        $rendered = (new MarkdownRenderer())->renderEnginePage($sections);

        // Substrings of the rendered page rather than file fixtures, and the blank lines around a heading are part of what each one pins.
        self::assertStringContainsString("# Engine behaviours\n\n## Target Resolution\n\n", $rendered);
        self::assertStringContainsString("\n\n### A lone dist file is synced\n\n", $rendered);
        self::assertStringContainsString('(../../tests/Scenario/Engine/TargetResolution/fixtures/lone-dist-file)', $rendered);
    }
}
