<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\PhpStan\PinnedValues;

use OrthoCode\StandardsSync\Rules\PhpStan\PinnedValues\PhpStanPinnedValues;
use OrthoCode\StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PhpStanPinnedValues::class)]
final class PhpStanPinnedValuesTest extends TestCase
{
    #[DataProvider('pinScenarios')]
    public function testPinsTheValues(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('pinScenarios')]
    public function testApplyIsIdempotent(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function pinScenarios(): iterable
    {
        yield 'a project without a config gets one holding the pins' => [
            null,
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	treatPhpDocTypesAsCertain: false
                    NEON,
            ),
        ];

        yield 'a deviating value is rewritten in place' => [
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 6
                    	treatPhpDocTypesAsCertain: true
                    NEON,
            ),
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 6
                    	treatPhpDocTypesAsCertain: false
                    NEON,
            ),
        ];

        yield 'an already-pinned value leaves the config unchanged' => [
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	treatPhpDocTypesAsCertain: false
                    NEON,
            ),
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	treatPhpDocTypesAsCertain: false
                    NEON,
            ),
        ];

        yield 'a missing key becomes the first child of its section' => [
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- a.neon

                    parameters:
                    	level: 6
                    NEON,
            ),
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- a.neon

                    parameters:
                    	treatPhpDocTypesAsCertain: false
                    	level: 6
                    NEON,
            ),
        ];

        yield 'a missing top-level section is appended at the end' => [
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- a.neon
                    NEON,
            ),
            FileContent::fromString(
                <<<'NEON'
                    includes:
                    	- a.neon

                    parameters:
                    	treatPhpDocTypesAsCertain: false
                    NEON,
            ),
        ];

        yield 'a trailing comment on the pinned line survives' => [
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	treatPhpDocTypesAsCertain: true # why not
                    NEON,
            ),
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	treatPhpDocTypesAsCertain: false # why not
                    NEON,
            ),
        ];

        yield 'a space-indented file keeps its indentation' => [
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                        level: 6
                    NEON,
            ),
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                        treatPhpDocTypesAsCertain: false
                        level: 6
                    NEON,
            ),
        ];
    }

    public function testCreatesNestedSectionsAlongTheWay(): void
    {
        $rule = new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'cache' => [
                    'nodesByStringCountMax' => 128,
                ],
            ],
        ]));

        $result = $rule->apply(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	level: 6
                    NEON,
            ),
        );

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	cache:
                    		nodesByStringCountMax: 128
                    	level: 6
                    NEON,
            ),
            $result,
        );
    }

    public function testPinsAStringNeedingQuotes(): void
    {
        $rule = new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'tmpDir' => 'var/php stan',
            ],
        ]));

        self::assertSame(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	tmpDir: 'var/php stan'
                    NEON,
            ),
            $rule->apply(null),
        );
    }

    public function testRefusesToPinAScalarOverASection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('holds a section');

        $rule = new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'cache' => 'simple',
            ],
        ]));

        $rule->apply(
            FileContent::fromString(
                <<<'NEON'
                    parameters:
                    	cache:
                    		nodesByStringCountMax: 128
                    NEON,
            ),
        );
    }

    public function testRefusesToPinBeneathAScalar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('holds a value');

        $this->rule()->apply(FileContent::fromString('parameters: true'));
    }

    public function testTargetsThePhpStanConfigCandidatesInLookupOrder(): void
    {
        self::assertSame('phpstan.neon | phpstan.neon.dist | phpstan.dist.neon', $this->rule()->target()->toString());
    }

    public function testDescriptionListsThePinnedPaths(): void
    {
        self::assertSame(
            'Pins the PHPStan config values: parameters.treatPhpDocTypesAsCertain.',
            $this->rule()->description(),
        );
    }

    private function rule(): PhpStanPinnedValues
    {
        return new PhpStanPinnedValues(values: PinnedValues::fromArray([
            'parameters' => [
                'treatPhpDocTypesAsCertain' => false,
            ],
        ]));
    }

}
