<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\PhpUnit\PinnedAttributes;

use OrthoCode\StandardsSync\Rules\PhpUnit\PinnedAttributes\PhpUnitPinnedAttributes;
use OrthoCode\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpUnitPinnedAttributes::class)]
final class PhpUnitPinnedAttributesTest extends TestCase
{
    #[DataProvider('pinScenarios')]
    public function testPinsTheAttributes(?string $current, string $expected): void
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
        yield 'a project without a config gets the skeleton holding the pins' => [
            null,
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true">
                        <testsuites>
                            <testsuite name="default">
                                <directory>tests</directory>
                            </testsuite>
                        </testsuites>
                    </phpunit>
                    XML,
            ),
        ];

        yield 'a deviating value is rewritten in place' => [
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="false" failOnRisky="true" colors="true" />
                    XML,
            ),
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="true" failOnRisky="true" colors="true" />
                    XML,
            ),
        ];

        yield 'a spelling phpunit reads as false is normalized' => [
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="1" failOnRisky="true" />
                    XML,
            ),
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="true" failOnRisky="true" />
                    XML,
            ),
        ];

        yield 'a spelling phpunit happens to read correctly is normalized too' => [
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="TRUE" failOnRisky="true" />
                    XML,
            ),
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="true" failOnRisky="true" />
                    XML,
            ),
        ];

        yield 'missing attributes are appended inline on a single-line tag' => [
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit bootstrap="vendor/autoload.php" />
                    XML,
            ),
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit bootstrap="vendor/autoload.php" failOnWarning="true" failOnRisky="true" />
                    XML,
            ),
        ];

        yield 'missing attributes each get a fresh line in a multiline tag' => [
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit bootstrap="vendor/autoload.php"
                             cacheDirectory=".phpunit.cache">
                    </phpunit>
                    XML,
            ),
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit bootstrap="vendor/autoload.php"
                             cacheDirectory=".phpunit.cache"
                             failOnWarning="true"
                             failOnRisky="true">
                    </phpunit>
                    XML,
            ),
        ];

        yield 'a compliant config is left byte-identical' => [
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="true" failOnRisky="true" />
                    XML,
            ),
            FileContent::fromString(
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <phpunit failOnWarning="true" failOnRisky="true" />
                    XML,
            ),
        ];
    }

    public function testRefusesAnEmptySet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('At least one attribute');

        new PhpUnitPinnedAttributes(attributes: []);
    }

    public function testTargetsThePhpUnitConfigCandidatesInLookupOrder(): void
    {
        self::assertSame('phpunit.xml | phpunit.dist.xml | phpunit.xml.dist', $this->rule()->target()->toString());
    }

    public function testDescriptionListsThePinnedAttributes(): void
    {
        self::assertSame(
            'Pins the PHPUnit root attributes: failOnWarning, failOnRisky.',
            $this->rule()->description(),
        );
    }

    public function testExplainsTheMissingConfig(): void
    {
        self::assertSame(
            'There is no PHPUnit config yet; one is created holding the pinned attributes.',
            $this->rule()->explain(null),
        );
    }

    public function testExplainsAMissingAttribute(): void
    {
        self::assertSame(
            'failOnWarning is not written and is added as "true"; failOnRisky is not written and is added as "true".',
            $this->rule()->explain(FileContent::fromString('<phpunit />')),
        );
    }

    public function testExplainsADeviatingValue(): void
    {
        self::assertSame(
            'failOnWarning is written as "false" where "true" is required.',
            $this->rule()->explain(FileContent::fromString('<phpunit failOnWarning="false" failOnRisky="true" />')),
        );
    }

    public function testExplainsTheSpellingPhpunitReadsAsFalse(): void
    {
        self::assertSame(
            'failOnWarning is written as "1", which phpunit silently reads as false, where "true" is required.',
            $this->rule()->explain(FileContent::fromString('<phpunit failOnWarning="1" failOnRisky="true" />')),
        );
    }

    public function testExplainsACompliantConfig(): void
    {
        self::assertSame(
            'All pinned attributes carry their required values.',
            $this->rule()->explain(FileContent::fromString('<phpunit failOnWarning="true" failOnRisky="true" />')),
        );
    }

    private function rule(): PhpUnitPinnedAttributes
    {
        return new PhpUnitPinnedAttributes(attributes: [
            'failOnWarning' => true,
            'failOnRisky' => true,
        ]);
    }
}
