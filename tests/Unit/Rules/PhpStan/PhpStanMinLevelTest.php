<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\PhpStan;

use AlleKnalle\StandardsSync\Core\Rule\OnMissing;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanLevel;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanMinLevel;
use InvalidArgumentException;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpStanMinLevel::class)]
final class PhpStanMinLevelTest extends TestCase
{
    #[DataProvider('floorScenarios')]
    public function testAppliesTheFloor(?string $current, ?string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('floorScenarios')]
    public function testApplyIsIdempotent(?string $current, ?string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string|null}> */
    public static function floorScenarios(): iterable
    {
        yield 'no phpstan config, no opinion' => [null, null];

        yield 'a lower level is raised to the floor' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 4
	paths:
		- src
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7
	paths:
		- src
NEON
            ),
        ];

        yield 'a stricter level is never touched' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 8
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 8
NEON
            ),
        ];

        yield 'a level equal to the floor stays put' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7
NEON
            ),
        ];

        yield 'max satisfies any floor' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: max
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: max
NEON
            ),
        ];

        yield 'a quoted lower level is raised' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: '4'
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7
NEON
            ),
        ];

        yield 'a missing level line is left to the imported ruleset' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	paths:
		- src
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	paths:
		- src
NEON
            ),
        ];

        yield 'a config without parameters stays put' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon
NEON
            ),
        ];

        yield 'a nested level key of an extension is never touched' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 4
	type_coverage:
		level: 9
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7
	type_coverage:
		level: 9
NEON
            ),
        ];

        yield 'a nested level key alone does not count as the written level' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	type_coverage:
		level: 2
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	type_coverage:
		level: 2
NEON
            ),
        ];

        yield 'a trailing comment on the level line survives the raise' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 4 # keep in step with CI
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7 # keep in step with CI
NEON
            ),
        ];
    }

    #[DataProvider('writeModeScenarios')]
    public function testWriteModeWritesTheFloorWhereNoLevelIsWritten(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->writeRule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('writeModeScenarios')]
    public function testWriteModeIsIdempotent(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->writeRule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function writeModeScenarios(): iterable
    {
        yield 'the floor becomes the first child of an existing parameters section' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	paths:
		- src
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 7
	paths:
		- src
NEON
            ),
        ];

        yield 'a config without parameters gains the section at the end' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 7
NEON
            ),
        ];

        yield 'a written level follows the normal floor logic' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 4
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 7
NEON
            ),
        ];
    }

    public function testWriteModeStillAbstainsOnAnAbsentConfig(): void
    {
        self::assertNull($this->writeRule()->apply(null));
    }

    public function testExplainsAMissingLevelInWriteMode(): void
    {
        $config = FileContent::fromString(
            <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon
NEON
        );

        self::assertSame('No PHPStan level is written; 7 is added as the minimum.', $this->writeRule()->explain($config));
    }

    public function testRefusesALevelValueItCannotJudge(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rule()->apply(
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: %level%
NEON
            ),
        );
    }

    public function testTargetsThePhpStanConfigCandidatesInLookupOrder(): void
    {
        self::assertSame('phpstan.neon | phpstan.neon.dist | phpstan.dist.neon', $this->rule()->target()->toString());
    }

    public function testDescribesAndExplainsTheFloor(): void
    {
        $rule = $this->rule();
        $lowLevel = FileContent::fromString(
            <<<'NEON'
parameters:
	level: 4
NEON
        );

        self::assertSame('Keeps the PHPStan level at or above 7.', $rule->description());
        self::assertSame('Level 4 is below the minimum of 7.', $rule->explain($lowLevel));
        self::assertSame('The PHPStan level is below the minimum of 7.', $rule->explain(null));
    }

    private function rule(): PhpStanMinLevel
    {
        return new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7));
    }

    private function writeRule(): PhpStanMinLevel
    {
        return new PhpStanMinLevel(minLevel: PhpStanLevel::fromInt(7), onMissing: OnMissing::Write);
    }

}
