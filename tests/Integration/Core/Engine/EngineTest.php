<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Core\Engine;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Engine\Differ;
use AlleKnalle\StandardsSync\Core\Engine\Engine;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Render\BlockRenderer;
use AlleKnalle\StandardsSync\Core\Resolve\ContentMergerRegistry;
use AlleKnalle\StandardsSync\Core\Resolve\FileSpecResolver;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Engine::class)]
final class EngineTest extends TestCase
{
    public function testPlanDoesNotTouchTheFilesystem(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/.editorconfig' => 'stale']);

        $this->engine($filesystem)->plan($this->config());

        self::assertSame([], $filesystem->written());
        self::assertSame(['/a/.editorconfig' => 'stale'], $filesystem->contents());
    }

    public function testApplyWritesOnlyDriftedFiles(): void
    {
        $inSync = $this->engine(new InMemoryFilesystem())->plan($this->config())->changes()[0]->desired();

        $filesystem = new InMemoryFilesystem([
            '/a/.editorconfig' => $inSync,
            '/b/.editorconfig' => 'stale',
        ]);
        $engine = $this->engine($filesystem);

        $engine->apply($engine->plan($this->config()));

        self::assertSame(['/b/.editorconfig'], $filesystem->written());
    }

    public function testApplyThenReplanReportsNoDrift(): void
    {
        $filesystem = new InMemoryFilesystem(['/a/.editorconfig' => 'stale']);
        $engine = $this->engine($filesystem);

        $engine->apply($engine->plan($this->config()));

        self::assertFalse($engine->plan($this->config())->hasDrift());
    }

    private function engine(InMemoryFilesystem $filesystem): Engine
    {
        return new Engine(
            new FileSpecResolver(ContentMergerRegistry::default()),
            new Differ($filesystem, [new BlockRenderer()]),
            $filesystem,
        );
    }

    private function config(): SyncConfig
    {
        return SyncConfig::create()
            ->withRoots(['/a', '/b'])
            ->withRuleSet(new class extends ComposableRuleSet {
                public function __construct()
                {
                    $this->addSpec(new FileSpec(
                        Path::fromString('.editorconfig'),
                        Label::fromString('test'),
                        'root = true',
                    ));
                }
            });
    }
}
