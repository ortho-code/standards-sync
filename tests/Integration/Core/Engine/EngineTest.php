<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Core\Engine;

use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Engine\Engine;
use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Lock\SyncLock;
use OrthoCode\StandardsSync\Core\Plan\Change;
use OrthoCode\StandardsSync\Core\Plan\ChangeKind;
use OrthoCode\StandardsSync\Core\Plan\ForgottenList;
use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Rules\Composer\Script\ComposerScript;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
use Closure;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Engine::class)]
final class EngineTest extends TestCase
{
    public function testPlanDoesNotTouchTheFilesystem(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/.editorconfig' => 'stale',
        ]);

        new Engine($filesystem)->plan($this->config());

        self::assertSame([], $filesystem->written());
        self::assertSame([
            '/a/.editorconfig' => 'stale',
        ], $filesystem->contents());
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
        $filesystem = new InMemoryFilesystem([
            '/a/.editorconfig' => 'stale',
        ]);
        $engine = new Engine($filesystem);

        $engine->apply($engine->plan($this->config()));

        self::assertFalse($engine->plan($this->config())->hasDrift());
    }

    public function testFansEachRuleAcrossEveryRoot(): void
    {
        $plan = new Engine(new InMemoryFilesystem())->plan($this->config());

        self::assertSame(
            ['/a/.editorconfig', '/b/.editorconfig'],
            array_map(static fn(Change $change): string => $change->path()->value(), $plan->changes()),
        );
    }

    public function testComposedRuleSetsFoldASharedLabelToTheChildContent(): void
    {
        $parent = $this->ruleSetWith($this->blockRule('.editorconfig', 'shared', 'parent'));
        $child = new class ($parent, $this->blockRule('.editorconfig', 'shared', 'child')) extends ComposableRuleSet {
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
            (int) strpos($desired, '# >>> two - managed >>>'),
            (int) strpos($desired, '# >>> one - managed >>>'),
        );
    }

    public function testResolvesTheOnlyExistingCandidateWhateverItsName(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/phpstan.dist.neon' => FileContent::fromString('old'),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(new ManagedBlock(
            FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon'),
            Label::fromString('test'),
            'level',
        )));

        $changes = new Engine($filesystem)->plan($config)->changes();

        self::assertCount(1, $changes);
        self::assertSame('/a/phpstan.dist.neon', $changes[0]->path()->value());
        self::assertSame(ChangeKind::Update, $changes[0]->kind());
        self::assertNull($changes[0]->shadowedBy());
    }

    public function testPrefersTheDistFileWhenALocalFileShadowsIt(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/phpstan.neon' => FileContent::fromString('local override'),
            '/a/phpstan.neon.dist' => FileContent::fromString('committed home'),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith($this->stubRule(
            FileTarget::fromStrings('phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'),
            static fn(?string $content): ?string => $content . FileContent::fromString('managed'),
        )));

        $changes = new Engine($filesystem)->plan($config)->changes();

        self::assertCount(1, $changes);
        self::assertSame('/a/phpstan.neon.dist', $changes[0]->path()->value());
        self::assertSame(FileContent::fromString('committed home'), $changes[0]->current());
        self::assertSame('/a/phpstan.neon', $changes[0]->shadowedBy()?->value());
    }

    public function testRefusesSeveralExistingDistFiles(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/phpstan.neon.dist' => FileContent::fromString('one home'),
            '/a/phpstan.dist.neon' => FileContent::fromString('another home'),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith($this->stubRule(
            FileTarget::fromStrings('phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'),
            static fn(?string $content): ?string => $content,
        )));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Both "/a/phpstan.neon.dist" and "/a/phpstan.dist.neon" exist; the standard has one committed home — remove all but one.');

        new Engine($filesystem)->plan($config);
    }

    public function testRefusesSeveralExistingNonDistFiles(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/a.conf' => FileContent::fromString('one'),
            '/a/b.conf' => FileContent::fromString('two'),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith($this->stubRule(
            FileTarget::fromStrings('a.conf', 'b.conf'),
            static fn(?string $content): ?string => $content,
        )));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Both "/a/a.conf" and "/a/b.conf" exist for one target; remove all but one.');

        new Engine($filesystem)->plan($config);
    }

    public function testTargetsTheFirstCandidateWhenNoneExist(): void
    {
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(new ManagedBlock(
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
        $filesystem = new InMemoryFilesystem([
            '/a/phpstan.dist.neon' => FileContent::fromString('old'),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(
            new ManagedBlock(
                FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon'),
                Label::fromString('one'),
                'a',
            ),
            new ManagedBlock(
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
            $this->stubRule('phpstan.neon', static fn(?string $content): ?string => $content),
        ));

        self::assertSame([], new Engine(new InMemoryFilesystem())->plan($config)->changes());
    }

    public function testRefusesARuleThatWantsTheFileDeleted(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/phpstan.neon' => FileContent::fromString('level'),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith(
            $this->stubRule('phpstan.neon', static fn(?string $content): ?string => null),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deletion is not supported');

        new Engine($filesystem)->plan($config);
    }

    public function testAttributesDriftToTheRuleThatChangedTheContent(): void
    {
        $satisfied = $this->blockRule('.gitignore', 'one', 'a');
        $missing = $this->blockRule('.gitignore', 'two', 'b');
        $filesystem = new InMemoryFilesystem([
            '/a/.gitignore' => (string) $satisfied->apply(null),
        ]);
        $config = SyncConfig::create()->withRoots(['/a'])->withRuleSet($this->ruleSetWith($satisfied, $missing));

        $drifting = new Engine($filesystem)->plan($config)->changes()[0]->driftingApplications();

        self::assertCount(1, $drifting);
        self::assertSame($missing, $drifting[0]->rule());
    }

    public function testContributionsToOneListFromSeparateRuleSetsFoldAsOneRule(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{}'),
        ]);
        $config = SyncConfig::create()
            ->withRoots(['/a'])
            ->withRuleSet($this->ruleSetWith(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check'])))
            ->withRuleSet($this->ruleSetWith(
                new ComposerScript(name: 'app-lint', commands: ['phpcs']),
                new ComposerScript(name: 'app-checks', commands: ['@app-lint']),
            ));

        $manifest = new Engine($filesystem)->plan($config)->changes()[0];

        self::assertCount(2, $manifest->applications());
        self::assertSame(
            'Runs "@app-sync-check", "@app-lint" in the composer script "app-checks", beside any commands the project adds.',
            $manifest->applications()[0]->rule()->description(),
        );
    }

    public function testContributionsResolvingToOneFileMergeWhateverCandidatesTheyDeclare(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/list.json5' => FileContent::fromString('a'),
        ]);
        $config = SyncConfig::create()
            ->withRoots(['/a'])
            ->withRuleSet($this->ruleSetWith($this->contribution(FileTarget::fromStrings('list.json', 'list.json5'), 'entries', 'a')))
            ->withRuleSet($this->ruleSetWith($this->contribution(FileTarget::fromStrings('list.json5', 'list.json'), 'entries', 'b')));

        [$list, $lock] = new Engine($filesystem)->plan($config)->changes();

        self::assertCount(1, $list->applications());
        self::assertSame(
            FileContent::fromString(
                <<<'TEXT'
                    a
                    b
                    TEXT,
            ),
            $list->desired(),
        );
        self::assertSame(['a', 'b'], SyncLock::fromJson($lock->desired(), $lock->path())->entries(Path::fromString('list.json5'), 'entries'));
    }

    public function testRefusesTwoRuleClassesContributingToOneList(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{}'),
        ]);
        $config = SyncConfig::create()
            ->withRoots(['/a'])
            ->withRuleSet($this->ruleSetWith(
                new ComposerScript(name: 'app-checks', commands: ['@app-sync-check']),
                $this->contribution(FileTarget::fromString('composer.json'), 'scripts.app-checks', '@app-lint'),
            ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~^Both .+ComposerScript and .+ contribute to "scripts\.app-checks" in "/a/composer\.json"; a list takes contributions from one rule class\.$~');

        new Engine($filesystem)->plan($config);
    }

    public function testTheLockIsPlannedAfterTheRootsFilesAndRecordsWhatWasDeclared(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{}'),
        ]);

        $changes = new Engine($filesystem)->plan($this->scriptConfig())->changes();

        self::assertSame(['/a/composer.json', '/a/standards-sync.lock'], array_map(static fn(Change $change): string => $change->path()->value(), $changes));
        self::assertSame(ChangeKind::Create, $changes[1]->kind());
        self::assertSame([], $changes[1]->applications());
        self::assertSame(
            SyncLock::create()->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check'])->toJson(),
            $changes[1]->desired(),
        );
    }

    public function testAMissingLockIsDriftEvenWhenEveryFileIsInSync(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{"scripts": {"app-checks": ["@app-sync-check"]}}'),
        ]);

        $plan = new Engine($filesystem)->plan($this->scriptConfig());

        self::assertCount(1, $plan->drift());
        self::assertSame('/a/standards-sync.lock', $plan->drift()[0]->path()->value());
    }

    public function testApplyingWritesTheLockSoTheNextPlanIsInSync(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{}'),
        ]);
        $engine = new Engine($filesystem);

        $engine->apply($engine->plan($this->scriptConfig()));

        self::assertContains('/a/standards-sync.lock', $filesystem->written());
        self::assertFalse($engine->plan($this->scriptConfig())->hasDrift());
    }

    public function testRetiredEntriesReachTheRuleFromTheLock(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{"scripts": {"app-checks": ["@app-sync-check", "@app-phpcs"]}}'),
            '/a/standards-sync.lock' => SyncLock::create()->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check', '@app-phpcs'])->toJson(),
        ]);

        $manifest = new Engine($filesystem)->plan($this->scriptConfig())->changes()[0];

        self::assertStringNotContainsString('@app-phpcs', $manifest->desired());
    }

    public function testAnAbsentFileKeepsWhatTheLockRecordedForIt(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/standards-sync.lock' => SyncLock::create()->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check'])->toJson(),
        ]);

        $plan = new Engine($filesystem)->plan($this->scriptConfig());

        self::assertFalse($plan->hasDrift());
        self::assertSame([], $plan->forgottenLists());
        self::assertCount(1, $plan->abstentions());
    }

    public function testAListNoRuleContributesToAnyMoreIsForgotten(): void
    {
        $filesystem = new InMemoryFilesystem([
            '/a/composer.json' => FileContent::fromString('{"scripts": {"app-checks": ["@app-sync-check"], "app-phpcs": ["phpcs"]}}'),
            '/a/standards-sync.lock' => SyncLock::create()
                ->withEntries(Path::fromString('composer.json'), 'scripts.app-checks', ['@app-sync-check'])
                ->withEntries(Path::fromString('composer.json'), 'scripts.app-phpcs', ['phpcs'])
                ->toJson(),
        ]);

        $plan = new Engine($filesystem)->plan($this->scriptConfig());

        self::assertEquals([new ForgottenList(Path::fromString('/a/composer.json'), 'scripts.app-phpcs')], $plan->forgottenLists());
    }

    public function testARootWithoutContributionsGetsNoLock(): void
    {
        $plan = new Engine(new InMemoryFilesystem())->plan($this->config());

        self::assertSame(
            ['/a/.editorconfig', '/b/.editorconfig'],
            array_map(static fn(Change $change): string => $change->path()->value(), $plan->changes()),
        );
    }

    private function scriptConfig(): SyncConfig
    {
        return SyncConfig::create()
            ->withRoots(['/a'])
            ->withRuleSet($this->ruleSetWith(new ComposerScript(name: 'app-checks', commands: ['@app-sync-check'])));
    }

    private function config(): SyncConfig
    {
        return SyncConfig::create()
            ->withRoots(['/a', '/b'])
            ->withRuleSet($this->ruleSetWith($this->blockRule('.editorconfig', 'test', 'root = true')));
    }

    private function blockRule(string $target, string $label, string $content): ManagedBlock
    {
        return new ManagedBlock(FileTarget::fromString($target), Label::fromString($label), $content);
    }

    /** @param Closure(?string): ?string $apply */
    private function stubRule(string|FileTarget $target, Closure $apply): Rule
    {
        $target = is_string($target) ? FileTarget::fromString($target) : $target;

        return new class ($target, $apply) implements Rule {
            /** @param Closure(?string): ?string $apply */
            public function __construct(
                private readonly FileTarget $target,
                private readonly Closure $apply,
            ) {}

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

    /** A contribution that writes its merged entries as the whole file, one per line. */
    private function contribution(FileTarget $target, string $listKey, string $entry): Rule&ContributesToList
    {
        return new readonly class ($target, $listKey, [$entry]) implements Rule, ContributesToList {
            /** @param non-empty-list<string> $entries */
            public function __construct(
                private FileTarget $target,
                private string $listKey,
                private array $entries,
            ) {}

            public function target(): FileTarget
            {
                return $this->target;
            }

            public function listKey(): string
            {
                return $this->listKey;
            }

            public function entries(): array
            {
                return $this->entries;
            }

            public function withMerged(ContributesToList $later): static
            {
                /** @var static $merged psalm types clone-with as a plain object */
                $merged = clone($this, [
                    'entries' => [...$this->entries, ...$later->entries()],
                ]);

                return $merged;
            }

            public function withRetired(array $retired): static
            {
                return $this;
            }

            public function apply(?string $content): ?string
            {
                return Lines::join($this->entries) . Lines::LINE_BREAK;
            }

            public function description(): string
            {
                return 'Stub contribution for engine tests.';
            }
        };
    }

    private function ruleSetWith(Rule ...$rules): ComposableRuleSet
    {
        return new class (...$rules) extends ComposableRuleSet {
            public function __construct(Rule ...$rules)
            {
                foreach ($rules as $rule) {
                    $this->addRule($rule);
                }
            }
        };
    }
}
