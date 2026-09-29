<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Composer\Script;

use OrthoCode\StandardsSync\Rules\Composer\Script\ScriptCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScriptCommand::class)]
final class ScriptCommandTest extends TestCase
{
    /** @return iterable<string, array{bool, string, bool}> */
    public static function candidates(): iterable
    {
        yield 'the command itself, strict' => [false, 'phpstan analyse', true];
        yield 'the command itself, accepting arguments' => [true, 'phpstan analyse', true];
        yield 'with arguments, strict' => [false, 'phpstan analyse --memory-limit=1G', false];
        yield 'with arguments, accepting them' => [true, 'phpstan analyse --memory-limit=1G', true];
        yield 'a longer word is not an argument' => [true, 'phpstan analyse-nothing', false];
        yield 'another command' => [true, 'phpstan', false];
    }

    #[DataProvider('candidates')]
    public function testMatches(bool $acceptsArguments, string $actual, bool $expected): void
    {
        self::assertSame($expected, ScriptCommand::fromString('phpstan analyse', $acceptsArguments)->matches($actual));
    }
}
