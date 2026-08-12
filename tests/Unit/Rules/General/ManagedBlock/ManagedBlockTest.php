<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\General\ManagedBlock;

use AlleKnalle\StandardsSync\Rules\General\ManagedBlock\Label;
use AlleKnalle\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use InvalidArgumentException;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManagedBlock::class)]
final class ManagedBlockTest extends TestCase
{
    #[DataProvider('singleBlockScenarios')]
    public function testAppliesTheManagedBlock(string $content, ?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule('x', 'test', $content)->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('singleBlockScenarios')]
    public function testApplyIsIdempotent(string $content, ?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule('x', 'test', $content)->apply($expected));
    }

    /** @return iterable<string, array{string, string|null, string}> */
    public static function singleBlockScenarios(): iterable
    {
        yield 'absent file becomes the block' => [
            'root = true',
            null,
            FileContent::fromString(
                <<<'FILE'
                    # >>> test (managed) >>>
                    root = true
                    # <<< test <<<
                    FILE
            ),
        ];

        yield 'existing unmarked content is kept and the block appended' => [
            'ignored/',
            FileContent::fromString('existing'),
            FileContent::fromString(
                <<<'FILE'
                    existing

                    # >>> test (managed) >>>
                    ignored/
                    # <<< test <<<
                    FILE
            ),
        ];

        yield 'existing block is replaced in place' => [
            'new',
            FileContent::fromString(
                <<<'FILE'
                    top
                    # >>> test (managed) >>>
                    old
                    # <<< test <<<
                    bottom
                    FILE
            ),
            FileContent::fromString(
                <<<'FILE'
                    top
                    # >>> test (managed) >>>
                    new
                    # <<< test <<<
                    bottom
                    FILE
            ),
        ];

        yield 'idempotent when block already matches' => [
            'root = true',
            FileContent::fromString(
                <<<'FILE'
                    # >>> test (managed) >>>
                    root = true
                    # <<< test <<<
                    FILE
            ),
            FileContent::fromString(
                <<<'FILE'
                    # >>> test (managed) >>>
                    root = true
                    # <<< test <<<
                    FILE
            ),
        ];
    }

    #[DataProvider('commentSyntaxByFileType')]
    public function testDerivesTheCommentSyntaxFromTheTargetFileType(string $path, string $expectedOpenMarker): void
    {
        self::assertStringContainsString($expectedOpenMarker, (string) $this->rule($path, 'test', 'x')->apply(null));
    }

    /** @return iterable<string, array{string, string}> */
    public static function commentSyntaxByFileType(): iterable
    {
        yield 'hash for an unlisted extension' => ['.editorconfig', '# >>> test (managed) >>>'];
        yield 'double slash for javascript' => ['eslint.config.js', '// >>> test (managed) >>>'];
        yield 'semicolon for ini' => ['php.ini', '; >>> test (managed) >>>'];
        yield 'a distribution suffix does not hide the real type' => ['php.ini.dist', '; >>> test (managed) >>>'];
    }

    public function testRejectsATargetWithoutLineComments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no line comments');

        $this->rule('composer.json', 'test', 'x');
    }

    public function testRejectsCandidatesThatDisagreeOnCommentSyntax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('disagree on comment syntax');

        new ManagedBlock(FileTarget::fromStrings('php.ini', 'php.conf'), Label::fromString('test'), 'x');
    }

    public function testTwoLabelsStackAsTwoBlocks(): void
    {
        $result = $this->rule('x', 'two', 'b')->apply($this->rule('x', 'one', 'a')->apply(null));

        self::assertStringContainsString('# >>> one (managed) >>>', (string) $result);
        self::assertStringContainsString('# >>> two (managed) >>>', (string) $result);
    }

    public function testReplacesAnExistingSameLabelBlockWhicheverRuleProducedIt(): void
    {
        $result = $this->rule('x', 'shared', 'child')->apply($this->rule('x', 'shared', 'parent')->apply(null));

        self::assertStringContainsString('child', (string) $result);
        self::assertStringNotContainsString('parent', (string) $result);
    }

    public function testDescriptionNamesTheLabelAndTheTarget(): void
    {
        self::assertSame(
            'Places the managed "test" block in .editorconfig.',
            $this->rule('.editorconfig', 'test', 'x')->description(),
        );
    }

    public function testDescriptionListsAllCandidatesOfAMultiCandidateTarget(): void
    {
        $rule = new ManagedBlock(
            FileTarget::fromStrings('phpstan.neon', 'phpstan.dist.neon'),
            Label::fromString('test'),
            'x',
        );

        self::assertSame('Places the managed "test" block in phpstan.neon | phpstan.dist.neon.', $rule->description());
    }

    private function rule(string $target, string $label, string $content): ManagedBlock
    {
        return new ManagedBlock(FileTarget::fromString($target), Label::fromString($label), $content);
    }

}
