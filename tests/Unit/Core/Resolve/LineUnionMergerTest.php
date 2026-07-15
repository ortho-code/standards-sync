<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Core\Resolve;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Resolve\LineUnionMerger;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineUnionMerger::class)]
final class LineUnionMergerTest extends TestCase
{
    /** @param non-empty-list<string> $contents */
    #[DataProvider('unionScenarios')]
    public function testUnionsLines(array $contents, string $expected): void
    {
        $specs = array_map(
            static fn (string $content): FileSpec => new FileSpec(
                Path::fromString('.gitignore'),
                Label::fromString('test'),
                $content,
            ),
            $contents,
        );

        self::assertSame($expected, (new LineUnionMerger())->merge($specs));
    }

    /** @return iterable<string, array{non-empty-list<string>, string}> */
    public static function unionScenarios(): iterable
    {
        yield 'dedups across specs keeping first occurrence' => [
            ["vendor/\n.idea/", ".idea/\nnode_modules/"],
            "vendor/\n.idea/\nnode_modules/",
        ];

        yield 'single spec passes through' => [
            ["a/\nb/"],
            "a/\nb/",
        ];
    }
}
