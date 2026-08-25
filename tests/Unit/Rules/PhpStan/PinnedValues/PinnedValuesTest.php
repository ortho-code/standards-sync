<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Rules\PhpStan\PinnedValues;

use StandardsSync\Rules\PhpStan\PinnedValues\PinnedValue;
use StandardsSync\Rules\PhpStan\PinnedValues\PinnedValues;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PinnedValues::class)]
final class PinnedValuesTest extends TestCase
{
    public function testFlattensNestedKeysToTypedLeaves(): void
    {
        $values = PinnedValues::fromArray([
            'parameters' => [
                'treatPhpDocTypesAsCertain' => false,
                'cache' => ['nodesByStringCountMax' => 128],
            ],
        ]);

        $leaves = $values->leaves();

        self::assertCount(2, $leaves);
        self::assertSame('parameters.treatPhpDocTypesAsCertain', $leaves[0]->dottedPath());
        self::assertFalse($leaves[0]->value());
        self::assertSame(['parameters', 'cache', 'nodesByStringCountMax'], $leaves[1]->path());
        self::assertSame(128, $leaves[1]->value());
    }

    #[DataProvider('invalidStructures')]
    public function testRejectsAnInvalidStructure(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);

        PinnedValues::fromArray($values);
    }

    /** @return iterable<string, array{array<mixed, mixed>}> */
    public static function invalidStructures(): iterable
    {
        yield 'nothing pinned' => [[]];
        yield 'an empty pinned section' => [['parameters' => []]];
        yield 'a non-string key' => [['parameters' => [0 => 'x']]];
        yield 'a float leaf' => [['parameters' => ['memoryLimitFactor' => 1.5]]];
        yield 'a null leaf' => [['parameters' => ['editorUrl' => null]]];
    }
}
