<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Core\Resolve;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Plan\DesiredFile;
use AlleKnalle\StandardsSync\Core\Resolve\ContentMergerRegistry;
use AlleKnalle\StandardsSync\Core\Resolve\FileSpecResolver;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSpecResolver::class)]
final class FileSpecResolverTest extends TestCase
{
    public function testFansEachSpecAcrossEveryRoot(): void
    {
        $config = SyncConfig::create()
            ->withRoots(['/a', '/b'])
            ->withRuleSet($this->ruleSetWith($this->spec('.editorconfig', 'test', 'root = true')));

        $paths = array_map(
            static fn (DesiredFile $desiredFile): string => $desiredFile->path()->value(),
            $this->resolver()->resolve($config),
        );

        self::assertSame(['/a/.editorconfig', '/b/.editorconfig'], $paths);
    }

    public function testComposedRuleSetsMergeASharedLabelIntoOneBlockWithChildWinning(): void
    {
        $parent = $this->ruleSetWith($this->spec('.editorconfig', 'shared', 'parent'));
        $child = new class($parent) extends ComposableRuleSet {
            public function __construct(ComposableRuleSet $parent)
            {
                $this->include($parent);
                $this->addSpec(new FileSpec(
                    Path::fromString('.editorconfig'),
                    Label::fromString('shared'),
                    'child',
                ));
            }
        };

        $desired = $this->resolver()->resolve(SyncConfig::create()->withRoots(['/a'])->withRuleSet($child));

        self::assertCount(1, $desired);
        self::assertCount(1, $desired[0]->blocks());
        self::assertSame('child', $desired[0]->blocks()[0]->content());
    }

    public function testSeparateLabelsBecomeSeparateBlocksInDeclarationOrder(): void
    {
        $config = SyncConfig::create()
            ->withRoots(['/a'])
            ->withRuleSet($this->ruleSetWith(
                $this->spec('.gitignore', 'one', 'a'),
                $this->spec('.gitignore', 'two', 'b'),
            ));

        $desired = $this->resolver()->resolve($config);

        self::assertCount(1, $desired);
        self::assertCount(2, $desired[0]->blocks());
        self::assertSame('one', $desired[0]->blocks()[0]->label()->value());
        self::assertSame('two', $desired[0]->blocks()[1]->label()->value());
    }

    private function resolver(): FileSpecResolver
    {
        return new FileSpecResolver(ContentMergerRegistry::default());
    }

    private function spec(string $path, string $label, string $content): FileSpec
    {
        return new FileSpec(Path::fromString($path), Label::fromString($label), $content);
    }

    private function ruleSetWith(FileSpec ...$specs): ComposableRuleSet
    {
        return new class(...$specs) extends ComposableRuleSet {
            public function __construct(FileSpec ...$specs)
            {
                foreach ($specs as $spec) {
                    $this->addSpec($spec);
                }
            }
        };
    }
}
