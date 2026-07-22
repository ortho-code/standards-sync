<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Presentation\Cli;

use AlleKnalle\StandardsSync\Rules\Block\Label;
use AlleKnalle\StandardsSync\Rules\Block\ManagedBlockRule;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Engine\Engine;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
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
            $this->blockRule('.editorconfig', 'shared', 'parent'),
            $this->blockRule('.editorconfig', 'shared', 'child'),
        );

        self::assertStringContainsString('(×2) ManagedBlockRule:', $report);
        self::assertSame(1, substr_count($report, 'Places the managed "shared" block'));
    }

    public function testDistinctDriftingRulesKeepTheirOwnLines(): void
    {
        $report = $this->renderFor(
            $this->blockRule('.gitignore', 'one', 'a'),
            $this->blockRule('.gitignore', 'two', 'b'),
        );

        self::assertStringContainsString('Places the managed "one" block', $report);
        self::assertStringContainsString('Places the managed "two" block', $report);
        self::assertStringNotContainsString('(×', $report);
    }

    private function renderFor(Rule ...$rules): string
    {
        $ruleSet = new class(...$rules) extends ComposableRuleSet {
            public function __construct(Rule ...$rules)
            {
                foreach ($rules as $rule) {
                    $this->addRule($rule);
                }
            }
        };
        $plan = new Engine(new InMemoryFilesystem())->plan(SyncConfig::create()->withRuleSet($ruleSet));

        $output = new BufferedOutput();
        (new DriftReport())->render($plan, new SymfonyStyle(new ArrayInput([]), $output));

        return $output->fetch();
    }

    private function blockRule(string $target, string $label, string $content): ManagedBlockRule
    {
        return new ManagedBlockRule(FileTarget::fromString($target), Label::fromString($label), $content);
    }
}
