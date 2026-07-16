<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Core\Engine;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Block\ManagedBlockRule;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Engine\Engine;
use AlleKnalle\StandardsSync\Core\Plan\Change;
use AlleKnalle\StandardsSync\Core\Plan\ChangeKind;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Engine::class)]
final class EngineTest extends TestCase
{
    public function testPlanDoesNotTouchTheFilesystem(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/.editorconfig' => 'stale']);

        new Engine($filesystem)->plan($this->config());

        self::assertSame([], $filesystem->written());
        self::assertSame(['/a/.editorconfig' => 'stale'], $filesystem->contents());
    }

    public function testApplyWritesOnlyDriftedFiles(): void
    {
        $inSync = new Engine(new InMemoryFilesystem())->plan($this->config())->changes()[0]->desired();

        $filesystem = new InMemoryFilesystem([
            '/a/.editorconfig' => $inSync,
            '/b/.editorconfig' => 'stale',
        ]);
        $engine = new Engine($filesystem);

        $engine->apply($engine->plan($this->config()));

        self::assertSame(['/b/.editorconfig'], $filesystem->written());
    }

    public function testApplyThenReplanReportsNoDrift(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/.editorconfig' => 'stale']);
        $engine = new Engine($filesystem);

        $engine->apply($engine->plan($this->config()));

        self::assertFalse($engine->plan($this->config())->hasDrift());
    }

    public function testFansEachRuleAcrossEveryRoot(): void
    {
        $plan = new Engine(new InMemoryFilesystem())->plan($this->config());

        self::assertSame(
            ['/a/.editorconfig', '/b/.editorconfig'],
            array_map(static fn (Change $change): string => $change->path()->value(), $plan->changes()),
        );
    }

    public function testComposedRuleSetsFoldASharedLabelToTheChildContent(): void
    {
        $parent = $this->ruleSetWith($this->blockRule('.editorconfig', 'shared', 'parent'));
        $child = new class($parent, $this->blockRule('.editorconfig', 'shared', 'child')) extends ComposableRuleSet {
            public function __construct(ComposableRuleSet $parent, Rule $own)
            {
                $this->include($parent);
                $this->addRule($own);
            }
        };
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($child);

        $changes = new Engine(new InMemoryFilesystem())->plan($config)->changes();

        self::assertCount(1, $changes);
        self::assertStringContainsString('child', $changes[0]->desired());
        self::assertStringNotContainsString('parent', $changes[0]->desired());
    }

    public function testSeparateLabelsBecomeSeparateBlocksInDeclarationOrder(): void
    {
        $config = SyncConfig::create()
            ->withRoots(['/a'])
            ->withRuleSet($this->ruleSetWith(
                $this->blockRule('.gitignore', 'one', 'a'),
                $this->blockRule('.gitignore', 'two', 'b'),
            ));

        $changes = new Engine(new InMemoryFilesystem())->plan($config)->changes();

        self::assertCount(1, $changes);
        $desired = $changes[0]->desired();
        self::assertLessThan(
            (int) strpos($desired, '# >>> two (managed) >>>'),
            (int) strpos($desired, '# >>> one (managed) >>>'),
        );
    }

    public function testResolvesTheFirstExistingCandidate(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/phpstan.dist.neon' => "old\n"]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(new ManagedBlockRule(
            FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon'),
            Label::fromString('test'),
            'level',
        )));

        $changes = new Engine($filesystem)->plan($config)->changes();

        self::assertCount(1, $changes);
        self::assertSame('/a/phpstan.dist.neon', $changes[0]->path()->value());
        self::assertSame(ChangeKind::Update, $changes[0]->kind());
    }

    public function testTargetsTheFirstCandidateWhenNoneExist(): void
    {
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(new ManagedBlockRule(
            FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon'),
            Label::fromString('test'),
            'level',
        )));

        $changes = new Engine(new InMemoryFilesystem())->plan($config)->changes();

        self::assertCount(1, $changes);
        self::assertSame('/a/phpstan.neon', $changes[0]->path()->value());
        self::assertSame(ChangeKind::Create, $changes[0]->kind());
    }

    public function testRulesWithDifferentCandidatesResolvingToTheSameFileFoldTogether(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/phpstan.dist.neon' => "old\n"]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(
            new ManagedBlockRule(
                FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon'),
                Label::fromString('one'),
                'a',
            ),
            new ManagedBlockRule(
                FileTarget::fromString('phpstan.dist.neon'),
                Label::fromString('two'),
                'b',
            ),
        ));

        $changes = new Engine($filesystem)->plan($config)->changes();

        self::assertCount(1, $changes);
        self::assertSame('/a/phpstan.dist.neon', $changes[0]->path()->value());
        self::assertCount(2, $changes[0]->applications());
    }

    public function testAbstainingRulesLeaveAnAbsentFileWithoutAChange(): void
    {
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(
            $this->stubRule('phpstan.neon', static fn (?string $content): ?string => $content),
        ));

        self::assertSame([], new Engine(new InMemoryFilesystem())->plan($config)->changes());
    }

    public function testRefusesARuleThatWantsTheFileDeleted(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/phpstan.neon' => "level\n"]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(
            $this->stubRule('phpstan.neon', static fn (?string $content): ?string => null),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deletion is not supported');

        new Engine($filesystem)->plan($config);
    }

    public function testAttributesDriftToTheRuleThatChangedTheContent(): void
    {
        $satisfied = $this->blockRule('.gitignore', 'one', 'a');
        $missing = $this->blockRule('.gitignore', 'two', 'b');
        $filesystem = new InMemoryFilesystem(['/a/.gitignore' => (string) $satisfied->apply(null)]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith($satisfied, $missing));

        $drifting = new Engine($filesystem)->plan($config)->changes()[0]->driftingApplications();

        self::assertCount(1, $drifting);
        self::assertSame($missing, $drifting[0]->rule());
    }

    private function config(): SyncConfig
    {
        return SyncConfig::create()
            ->withRoots(['/a', '/b'])
            ->withRuleSet($this->ruleSetWith($this->blockRule('.editorconfig', 'test', 'root = true')));
    }

    private function blockRule(string $target, string $label, string $content): ManagedBlockRule
    {
        return new ManagedBlockRule(FileTarget::fromString($target), Label::fromString($label), $content);
    }

    /** @param Closure(?string): ?string $apply */
    private function stubRule(string $target, Closure $apply): Rule
    {
        return new class(FileTarget::fromString($target), $apply) implements Rule {
            /** @param Closure(?string): ?string $apply */
            public function __construct(
                private readonly FileTarget $target,
                private readonly Closure $apply,
            ) {
            }

            public function target(): FileTarget
            {
                return $this->target;
            }

            public function apply(?string $content): ?string
            {
                return ($this->apply)($content);
            }

            public function description(): string
            {
                return 'Stub rule for engine tests.';
            }
        };
    }

    private function ruleSetWith(Rule ...$rules): ComposableRuleSet
    {
        return new class(...$rules) extends ComposableRuleSet {
            public function __construct(Rule ...$rules)
            {
                foreach ($rules as $rule) {
                    $this->addRule($rule);
                }
            }
        };
    }
}
