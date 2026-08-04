<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\Psalm\LoosestErrorLevel;

use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmErrorLevel;
use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmLoosestErrorLevel;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PsalmLoosestErrorLevel::class)]
final class PsalmLoosestErrorLevelTest extends TestCase
{
    #[DataProvider('limitScenarios')]
    public function testAppliesTheLimit(?string $current, ?string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('limitScenarios')]
    public function testApplyIsIdempotent(?string $current, ?string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string|null}> */
    public static function limitScenarios(): iterable
    {
        yield 'no psalm config: one is created at the limit' => [
            null,
            FileContent::fromString(
                <<<'XML'
<?xml version="1.0"?>
<psalm errorLevel="4" xmlns="https://getpsalm.org/schema/config">
    <projectFiles>
        <directory name="src" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
XML
            ),
        ];

        yield 'a looser level is lowered to the limit' => [
            FileContent::fromString(
                <<<'XML'
<?xml version="1.0"?>
<psalm errorLevel="8">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
XML
            ),
            FileContent::fromString(
                <<<'XML'
<?xml version="1.0"?>
<psalm errorLevel="4">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
XML
            ),
        ];

        $stricter = FileContent::fromString(
            <<<'XML'
<?xml version="1.0"?>
<psalm errorLevel="2">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
XML
        );
        yield 'a stricter level is never touched' => [$stricter, $stricter];

        $equal = FileContent::fromString(
            <<<'XML'
<?xml version="1.0"?>
<psalm errorLevel="4">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
XML
        );
        yield 'an equal level is never touched' => [$equal, $equal];

        yield 'an absent errorLevel is made explicit as the default' => [
            FileContent::fromString(
                <<<'XML'
<?xml version="1.0"?>
<psalm resolveFromConfigFile="true">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
XML
            ),
            FileContent::fromString(
                <<<'XML'
<?xml version="1.0"?>
<psalm resolveFromConfigFile="true" errorLevel="2">
    <projectFiles>
        <directory name="src" />
    </projectFiles>
</psalm>
XML
            ),
        ];
    }

    public function testAStricterLimitCapsTheMadeExplicitDefault(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
<?xml version="1.0"?>
<psalm resolveFromConfigFile="true">
</psalm>
XML
        );

        $rule = new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(1));

        self::assertStringContainsString('errorLevel="1"', (string) $rule->apply($content));
    }

    public function testExposesTheLimitForOrgPinTests(): void
    {
        self::assertSame(4, $this->rule()->loosest()->value());
    }

    public function testExplainsTheMissingConfig(): void
    {
        self::assertSame('There is no Psalm config yet; one is created with error level 4.', $this->rule()->explain(null));
    }

    public function testExplainsTheAbsentAttribute(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
<psalm>
</psalm>
XML
        );

        self::assertSame('No errorLevel is written; the implicit default is made explicit as 2.', $this->rule()->explain($content));
    }

    public function testExplainsALooserLevelInTheInvertedScaleWording(): void
    {
        $content = FileContent::fromString(
            <<<'XML'
<psalm errorLevel="7">
</psalm>
XML
        );

        self::assertSame('Level 7 is looser than the loosest allowed 4.', $this->rule()->explain($content));
    }

    private function rule(): PsalmLoosestErrorLevel
    {
        return new PsalmLoosestErrorLevel(loosest: PsalmErrorLevel::fromInt(4));
    }
}
