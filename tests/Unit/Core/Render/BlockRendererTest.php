<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Core\Render;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Plan\DesiredFile;
use AlleKnalle\StandardsSync\Core\Plan\ManagedBlock;
use AlleKnalle\StandardsSync\Core\Render\BlockRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlockRenderer::class)]
final class BlockRendererTest extends TestCase
{
    private BlockRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new BlockRenderer();
    }

    #[DataProvider('singleBlockScenarios')]
    public function testRendersASingleManagedBlock(string $content, ?string $current, string $expected): void
    {
        $file = new DesiredFile(
            Path::fromString('x'),
            [new ManagedBlock(Label::fromString('test'), $content)],
        );

        self::assertSame($expected, $this->renderer->render($file, $current));
    }

    /** @return iterable<string, array{string, string|null, string}> */
    public static function singleBlockScenarios(): iterable
    {
        yield 'absent file becomes the block' => [
            'root = true',
            null,
            "# >>> test (managed) >>>\nroot = true\n# <<< test <<<\n",
        ];

        yield 'existing unmarked content is kept and the block appended' => [
            'ignored/',
            "existing\n",
            "existing\n\n# >>> test (managed) >>>\nignored/\n# <<< test <<<\n",
        ];

        yield 'existing block is replaced in place' => [
            'new',
            "top\n# >>> test (managed) >>>\nold\n# <<< test <<<\nbottom\n",
            "top\n# >>> test (managed) >>>\nnew\n# <<< test <<<\nbottom\n",
        ];

        yield 'idempotent when block already matches' => [
            'root = true',
            "# >>> test (managed) >>>\nroot = true\n# <<< test <<<\n",
            "# >>> test (managed) >>>\nroot = true\n# <<< test <<<\n",
        ];
    }

    #[DataProvider('commentSyntaxByFileType')]
    public function testDerivesTheCommentSyntaxFromTheFileType(string $path, string $expectedOpenMarker): void
    {
        $file = new DesiredFile(
            Path::fromString($path),
            [new ManagedBlock(Label::fromString('test'), 'x')],
        );

        self::assertStringContainsString($expectedOpenMarker, $this->renderer->render($file, null));
    }

    /** @return iterable<string, array{string, string}> */
    public static function commentSyntaxByFileType(): iterable
    {
        yield 'hash for an unlisted extension' => ['.editorconfig', '# >>> test (managed) >>>'];
        yield 'double slash for javascript' => ['eslint.config.js', '// >>> test (managed) >>>'];
        yield 'semicolon for ini' => ['php.ini', '; >>> test (managed) >>>'];
    }

    public function testAppliesEveryBlockForAMultiLabelFile(): void
    {
        $file = new DesiredFile(Path::fromString('x'), [
            new ManagedBlock(Label::fromString('one'), 'a'),
            new ManagedBlock(Label::fromString('two'), 'b'),
        ]);

        $result = $this->renderer->render($file, null);

        self::assertStringContainsString('# >>> one (managed) >>>', $result);
        self::assertStringContainsString('# >>> two (managed) >>>', $result);
    }
}
