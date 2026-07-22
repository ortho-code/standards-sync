<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\PhpStan;

use AlleKnalle\StandardsSync\Core\Rule\OnMissing;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanLevel;
use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanMinLevel;
use InvalidArgumentException;
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
            "parameters:\n\tlevel: 4\n\tpaths:\n\t\t- src\n",
            "parameters:\n\tlevel: 7\n\tpaths:\n\t\t- src\n",
        ];

        yield 'a stricter level is never touched' => [
            "parameters:\n\tlevel: 8\n",
            "parameters:\n\tlevel: 8\n",
        ];

        yield 'a level equal to the floor stays put' => [
            "parameters:\n\tlevel: 7\n",
            "parameters:\n\tlevel: 7\n",
        ];

        yield 'max satisfies any floor' => [
            "parameters:\n\tlevel: max\n",
            "parameters:\n\tlevel: max\n",
        ];

        yield 'a quoted lower level is raised' => [
            "parameters:\n\tlevel: '4'\n",
            "parameters:\n\tlevel: 7\n",
        ];

        yield 'a missing level line is left to the imported ruleset' => [
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tpaths:\n\t\t- src\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tpaths:\n\t\t- src\n",
        ];

        yield 'a config without parameters stays put' => [
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n",
        ];

        yield 'a nested level key of an extension is never touched' => [
            "parameters:\n\tlevel: 4\n\ttype_coverage:\n\t\tlevel: 9\n",
            "parameters:\n\tlevel: 7\n\ttype_coverage:\n\t\tlevel: 9\n",
        ];

        yield 'a nested level key alone does not count as the written level' => [
            "parameters:\n\ttype_coverage:\n\t\tlevel: 2\n",
            "parameters:\n\ttype_coverage:\n\t\tlevel: 2\n",
        ];

        yield 'a trailing comment on the level line survives the raise' => [
            "parameters:\n\tlevel: 4 # keep in step with CI\n",
            "parameters:\n\tlevel: 7 # keep in step with CI\n",
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
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tpaths:\n\t\t- src\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tlevel: 7\n\tpaths:\n\t\t- src\n",
        ];

        yield 'a config without parameters gains the section at the end' => [
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tlevel: 7\n",
        ];

        yield 'a written level follows the normal floor logic' => [
            "parameters:\n\tlevel: 4\n",
            "parameters:\n\tlevel: 7\n",
        ];
    }

    public function testWriteModeStillAbstainsOnAnAbsentConfig(): void
    {
        self::assertNull($this->writeRule()->apply(null));
    }

    public function testExplainsAMissingLevelInWriteMode(): void
    {
        self::assertSame(
            'No PHPStan level is written; 7 is added as the minimum.',
            $this->writeRule()->explain("includes:\n\t- vendor/acme/standards/phpstan.neon\n"),
        );
    }

    public function testRefusesALevelValueItCannotJudge(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rule()->apply("parameters:\n\tlevel: %level%\n");
    }

    public function testTargetsThePhpStanConfigCandidatesInLookupOrder(): void
    {
        self::assertSame('phpstan.neon | phpstan.neon.dist | phpstan.dist.neon', $this->rule()->target()->toString());
    }

    public function testDescribesAndExplainsTheFloor(): void
    {
        $rule = $this->rule();

        self::assertSame('Keeps the PHPStan level at or above 7.', $rule->description());
        self::assertSame('Level 4 is below the minimum of 7.', $rule->explain("parameters:\n\tlevel: 4\n"));
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
