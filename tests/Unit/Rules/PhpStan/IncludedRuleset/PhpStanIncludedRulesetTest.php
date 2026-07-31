<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\PhpStan\IncludedRuleset;

use AlleKnalle\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use InvalidArgumentException;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PhpStanIncludedRuleset::class)]
final class PhpStanIncludedRulesetTest extends TestCase
{
    private const string IMPORT = 'vendor/acme/standards/phpstan.neon';

    #[DataProvider('importScenarios')]
    public function testAppliesTheImport(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('importScenarios')]
    public function testApplyIsIdempotent(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function importScenarios(): iterable
    {
        yield 'a project without a config gets one created, holding just the import' => [
            null,
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon
NEON
            ),
        ];

        yield 'an existing includes section gains the entry after its last entry' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- phpstan-baseline.neon

parameters:
	level: 6
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- phpstan-baseline.neon
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 6
NEON
            ),
        ];

        yield 'a config without includes gains the section at the top' => [
            FileContent::fromString(
                <<<'NEON'
parameters:
	level: 6
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 6
NEON
            ),
        ];

        yield 'an empty includes section gains the entry' => [
            FileContent::fromString(
                <<<'NEON'
includes:
parameters:
	level: 6
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon
parameters:
	level: 6
NEON
            ),
        ];

        yield 'an already-included import leaves the config unchanged' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 6
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- vendor/acme/standards/phpstan.neon

parameters:
	level: 6
NEON
            ),
        ];

        yield 'a quoted include of the same file counts as included' => [
            FileContent::fromString(
                <<<'NEON'
includes:
	- 'vendor/acme/standards/phpstan.neon'
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
	- 'vendor/acme/standards/phpstan.neon'
NEON
            ),
        ];

        yield 'the inserted entry copies the indentation of a space-indented file' => [
            FileContent::fromString(
                <<<'NEON'
includes:
    - phpstan-baseline.neon
parameters:
    level: 6
NEON
            ),
            FileContent::fromString(
                <<<'NEON'
includes:
    - phpstan-baseline.neon
    - vendor/acme/standards/phpstan.neon
parameters:
    level: 6
NEON
            ),
        ];
    }

    public function testRefusesAnInlineIncludesList(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('block list');

        $this->rule()->apply(FileContent::fromString('includes: [phpstan-baseline.neon]'));
    }

    public function testRejectsAnEmptyRulesetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhpStanIncludedRuleset(ruleset: ' ');
    }

    public function testTargetsThePhpStanConfigCandidatesInLookupOrder(): void
    {
        self::assertSame('phpstan.neon | phpstan.neon.dist | phpstan.dist.neon', $this->rule()->target()->toString());
    }

    public function testDescribesAndExplainsTheImport(): void
    {
        $rule = $this->rule();

        self::assertSame('Ensures the PHPStan config includes "vendor/acme/standards/phpstan.neon".', $rule->description());
        self::assertSame('There is no PHPStan config yet; one is created including "vendor/acme/standards/phpstan.neon".', $rule->explain(null));
        self::assertSame('The PHPStan config does not include "vendor/acme/standards/phpstan.neon".', $rule->explain(FileContent::fromString('parameters:')));
    }

    private function rule(): PhpStanIncludedRuleset
    {
        return new PhpStanIncludedRuleset(ruleset: self::IMPORT);
    }

}
