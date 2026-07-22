<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\PhpStan;

use AlleKnalle\StandardsSync\Rules\PhpStan\PhpStanImportRule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PhpStanImportRule::class)]
final class PhpStanImportRuleTest extends TestCase
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
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n",
        ];

        yield 'an existing includes section gains the entry after its last entry' => [
            "includes:\n\t- phpstan-baseline.neon\n\nparameters:\n\tlevel: 6\n",
            "includes:\n\t- phpstan-baseline.neon\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tlevel: 6\n",
        ];

        yield 'a config without includes gains the section at the top' => [
            "parameters:\n\tlevel: 6\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tlevel: 6\n",
        ];

        yield 'an empty includes section gains the entry' => [
            "includes:\nparameters:\n\tlevel: 6\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\nparameters:\n\tlevel: 6\n",
        ];

        yield 'an already-included import leaves the config unchanged' => [
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tlevel: 6\n",
            "includes:\n\t- vendor/acme/standards/phpstan.neon\n\nparameters:\n\tlevel: 6\n",
        ];

        yield 'a quoted include of the same file counts as included' => [
            "includes:\n\t- 'vendor/acme/standards/phpstan.neon'\n",
            "includes:\n\t- 'vendor/acme/standards/phpstan.neon'\n",
        ];

        yield 'the inserted entry copies the indentation of a space-indented file' => [
            "includes:\n    - phpstan-baseline.neon\nparameters:\n    level: 6\n",
            "includes:\n    - phpstan-baseline.neon\n    - vendor/acme/standards/phpstan.neon\nparameters:\n    level: 6\n",
        ];
    }

    public function testRefusesAnInlineIncludesList(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('block list');

        $this->rule()->apply("includes: [phpstan-baseline.neon]\n");
    }

    public function testRejectsAnEmptyImportPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhpStanImportRule(import: ' ');
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
        self::assertSame('The PHPStan config does not include "vendor/acme/standards/phpstan.neon".', $rule->explain("parameters:\n"));
    }

    private function rule(): PhpStanImportRule
    {
        return new PhpStanImportRule(import: self::IMPORT);
    }
}
