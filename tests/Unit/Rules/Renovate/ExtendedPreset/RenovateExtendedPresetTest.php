<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Rules\Renovate\ExtendedPreset;

use StandardsSync\Core\Filesystem\Path;
use StandardsSync\Rules\Renovate\ExtendedPreset\RenovateExtendedPreset;
use StandardsSync\Rules\Renovate\RenovateConfigFormat;
use StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RenovateExtendedPreset::class)]
final class RenovateExtendedPresetTest extends TestCase
{
    private const string PRESET = 'local>acme/renovate-config';

    public function testRefusesAnEmptyPresetReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A preset reference is one non-empty line.');

        new RenovateExtendedPreset(preset: ' ');
    }

    public function testRefusesAMultiLinePresetReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A preset reference is one non-empty line.');

        new RenovateExtendedPreset(preset: "a\nb");
    }

    public function testRefusesAnEmptyComment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A rule comment is one non-empty line.');

        new RenovateExtendedPreset(preset: self::PRESET, comment: ' ');
    }

    public function testTheCreationCandidateLeadsTheTarget(): void
    {
        $candidates = array_map(
            static fn (Path $path): string => $path->value(),
            new RenovateExtendedPreset(preset: self::PRESET)->target()->candidates(),
        );

        self::assertSame('renovate.json', $candidates[0]);
        self::assertContains('renovate.json5', $candidates);
        self::assertContains('.github/renovate.json', $candidates);
        self::assertContains('.renovaterc', $candidates);
        self::assertCount(13, $candidates);
    }

    public function testCreatingAsJson5MovesThatNameToTheFront(): void
    {
        $candidates = array_map(
            static fn (Path $path): string => $path->value(),
            new RenovateExtendedPreset(preset: self::PRESET, createAs: RenovateConfigFormat::Json5)->target()->candidates(),
        );

        self::assertSame('renovate.json5', $candidates[0]);
        self::assertContains('renovate.json', $candidates);
        self::assertCount(13, $candidates);
    }

    public function testCreatesAJsonConfigFromNothing(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'JSON'
                    {
                        "extends": [
                            "local>acme/renovate-config"
                        ]
                    }
                    JSON
            ),
            new RenovateExtendedPreset(preset: self::PRESET)->apply(null),
        );
    }

    public function testCreatesAJson5ConfigFromNothingWithTheComment(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        "extends": [
                            "local>acme/renovate-config" // org standard
                        ]
                    }
                    JSON5
            ),
            new RenovateExtendedPreset(preset: self::PRESET, createAs: RenovateConfigFormat::Json5, comment: 'org standard')->apply(null),
        );
    }

    public function testEditsAJson5FileAtItsResolvedPath(): void
    {
        $content = FileContent::fromString(
            <<<'JSON5'
                {
                  // keep majors quiet
                  extends: [
                    'config:recommended',
                  ],
                }
                JSON5
        );

        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                      // keep majors quiet
                      extends: [
                        'config:recommended',
                        'local>acme/renovate-config',
                      ],
                    }
                    JSON5
            ),
            new RenovateExtendedPreset(preset: self::PRESET)->applyAt(Path::fromString('renovate.json5'), $content),
        );
    }

    public function testRefusesAJsoncCandidate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"renovate.jsonc" is a JSONC config, which this standard does not manage');

        new RenovateExtendedPreset(preset: self::PRESET)->applyAt(Path::fromString('renovate.jsonc'), "{}\n");
    }

    public function testRefusesALenientJsonConfig(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    // a comment renovate tolerates but strict JSON forbids
                    "extends": []
                }
                JSON
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"renovate.json" is not strict JSON');

        new RenovateExtendedPreset(preset: self::PRESET)->applyAt(Path::fromString('renovate.json'), $content);
    }

    public function testTreatsTheExtensionlessRenovatercAsStrictJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('".renovaterc" is not strict JSON');

        new RenovateExtendedPreset(preset: self::PRESET)->applyAt(Path::fromString('.renovaterc'), "{ // lenient\n}\n");
    }

    public function testDispatchesAJson5NameInAPlatformDirectory(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'JSON5'
                    {
                        "extends": [
                            "local>acme/renovate-config"
                        ]
                    }
                    JSON5
            ),
            new RenovateExtendedPreset(preset: self::PRESET)->applyAt(Path::fromString('.github/renovate.json5'), null),
        );
    }

    public function testLeavesACompliantJsonConfigByteIdentical(): void
    {
        $content = FileContent::fromString(
            <<<'JSON'
                {
                    "extends": [
                        "local>acme/renovate-config"
                    ]
                }
                JSON
        );

        self::assertSame($content, new RenovateExtendedPreset(preset: self::PRESET)->applyAt(Path::fromString('renovate.json'), $content));
    }

    public function testDescribesItself(): void
    {
        self::assertSame(
            'Ensures the renovate config extends "local>acme/renovate-config".',
            new RenovateExtendedPreset(preset: self::PRESET)->description(),
        );
    }

    public function testExplainsCreation(): void
    {
        self::assertSame(
            'There is no renovate config yet; one is created extending "local>acme/renovate-config".',
            new RenovateExtendedPreset(preset: self::PRESET)->explain(null),
        );
    }

    public function testExplainsAMissingEntry(): void
    {
        self::assertSame(
            'The renovate config does not extend "local>acme/renovate-config".',
            new RenovateExtendedPreset(preset: self::PRESET)->explain("{}\n"),
        );
    }

    public function testExplainsAMissingComment(): void
    {
        self::assertSame(
            'The "local>acme/renovate-config" entry is not annotated with the enforced comment.',
            new RenovateExtendedPreset(preset: self::PRESET, comment: 'org standard')
                ->explain("{ \"extends\": [\"local>acme/renovate-config\"] }\n"),
        );
    }
}
