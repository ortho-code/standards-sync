<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Presentation\Cli;

use AlleKnalle\StandardsSync\Rules\General\ManagedBlock\Label;
use AlleKnalle\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Engine\Engine;
use AlleKnalle\StandardsSync\Core\Plan\Plan;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
use AlleKnalle\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
use AlleKnalle\StandardsSync\Rules\Composer\Requirement\VersionConstraint;
use AlleKnalle\StandardsSync\Presentation\Cli\DriftReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(DriftReport::class)]
final class DriftReportTest extends TestCase
{
    public function testIndistinguishableDriftingRulesCollapseIntoOneCountedLine(): void
    {
        // The override pattern: two same-label rules both change the running content and render identical lines.
        $report = $this->renderFor(
            new InMemoryFilesystem(),
            $this->blockRule('.editorconfig', 'shared', 'parent'),
            $this->blockRule('.editorconfig', 'shared', 'child'),
        );

        self::assertStringContainsString('(×2) ManagedBlock:', $report);
        self::assertSame(1, substr_count($report, 'Places the managed "shared" block'));
    }

    public function testDistinctDriftingRulesKeepTheirOwnLines(): void
    {
        $report = $this->renderFor(
            new InMemoryFilesystem(),
            $this->blockRule('.gitignore', 'one', 'a'),
            $this->blockRule('.gitignore', 'two', 'b'),
        );

        self::assertStringContainsString('Places the managed "one" block', $report);
        self::assertStringContainsString('Places the managed "two" block', $report);
        self::assertStringNotContainsString('(×', $report);
    }

    public function testNotesTheLocalFileShadowingADriftingDistTarget(): void
    {
        $filesystem = new InMemoryFilesystem([
            './phpstan.neon' => "local override\n",
            './phpstan.neon.dist' => "committed home\n",
        ]);

        $report = $this->renderFor($filesystem, $this->blockRuleWithCandidates(['phpstan.neon', 'phpstan.neon.dist'], 'org', 'level'));

        self::assertStringContainsString('./phpstan.neon exists and replaces ./phpstan.neon.dist for tool runs; the standard syncs to the dist file.', $report);
        self::assertStringContainsString('UPDATE ./phpstan.neon.dist', $report);
    }

    public function testNotesTheLocalFileEvenWhenTheDistTargetIsInSync(): void
    {
        $rule = $this->blockRuleWithCandidates(['phpstan.neon', 'phpstan.neon.dist'], 'org', 'level');
        $filesystem = new InMemoryFilesystem([
            './phpstan.neon' => "local override\n",
            './phpstan.neon.dist' => (string) $rule->apply(null),
        ]);

        $report = $this->renderFor($filesystem, $rule);

        self::assertStringContainsString('./phpstan.neon exists and replaces ./phpstan.neon.dist for tool runs; the standard syncs to the dist file.', $report);
        self::assertStringContainsString('All managed files are in sync.', $report);
    }

    /** A declared rule that quietly does nothing is what the note exists to prevent, so it is told even on an otherwise clean run. */
    public function testNotesAFileEveryRuleAbstainedOn(): void
    {
        $report = $this->renderFor(new InMemoryFilesystem(), $this->abstainingRule('phpstan/phpstan'));

        self::assertStringContainsString(' NOTE ./composer.json does not exist; nothing was enforced there (ComposerRequirement).', $report);
        self::assertStringContainsString('All managed files are in sync.', $report);
    }

    public function testTheAbstentionNoteCountsRepeatedRules(): void
    {
        $report = $this->renderFor(
            new InMemoryFilesystem(),
            $this->abstainingRule('phpstan/phpstan'),
            $this->abstainingRule('rector/rector'),
            $this->abstainingRule('vimeo/psalm'),
        );

        self::assertStringContainsString('nothing was enforced there (ComposerRequirement ×3).', $report);
    }

    public function testAnAbstainedFileIsNotDrift(): void
    {
        $plan = $this->planFor(new InMemoryFilesystem(), $this->abstainingRule('phpstan/phpstan'));

        self::assertFalse($plan->hasDrift());
        self::assertSame([], $plan->changes());
        self::assertCount(1, $plan->abstentions());
    }

    private function abstainingRule(string $package): ComposerRequirement
    {
        return new ComposerRequirement(package: $package, constraint: VersionConstraint::fromString('^1.0'));
    }

    private function renderFor(InMemoryFilesystem $filesystem, Rule ...$rules): string
    {
        $output = new BufferedOutput();
        (new DriftReport())->render($this->planFor($filesystem, ...$rules), new SymfonyStyle(new ArrayInput([]), $output));

        return $output->fetch();
    }

    private function planFor(InMemoryFilesystem $filesystem, Rule ...$rules): Plan
    {
        $ruleSet = new class(...$rules) extends ComposableRuleSet {
            public function __construct(Rule ...$rules)
            {
                foreach ($rules as $rule) {
                    $this->addRule($rule);
                }
            }
        };

        return new Engine($filesystem)->plan(SyncConfig::create()->withRuleSet($ruleSet));
    }

    /** @param non-empty-list<string> $candidates */
    private function blockRuleWithCandidates(array $candidates, string $label, string $content): ManagedBlock
    {
        return new ManagedBlock(FileTarget::fromStrings(...$candidates), Label::fromString($label), $content);
    }

    private function blockRule(string $target, string $label, string $content): ManagedBlock
    {
        return new ManagedBlock(FileTarget::fromString($target), Label::fromString($label), $content);
    }
}
