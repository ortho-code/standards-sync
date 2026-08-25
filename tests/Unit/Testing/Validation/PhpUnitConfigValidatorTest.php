<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Testing\Validation;

use StandardsSync\Testing\FileContent;
use StandardsSync\Testing\Validation\PhpUnitConfigValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PhpUnitConfigValidator::class)]
final class PhpUnitConfigValidatorTest extends TestCase
{
    public function testIgnoresAFileThatIsNoPhpUnitConfig(): void
    {
        $this->expectNotToPerformAssertions();

        new PhpUnitConfigValidator()->assertValid('./other.xml', "<foo><bar></foo>\n");
    }

    public function testAcceptsAConfigTheSchemaAllows(): void
    {
        $this->expectNotToPerformAssertions();

        $content = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <phpunit bootstrap="vendor/autoload.php" failOnWarning="true">
                    <testsuites>
                        <testsuite name="default">
                            <directory>tests</directory>
                        </testsuite>
                    </testsuites>
                </phpunit>
                XML
        );

        new PhpUnitConfigValidator()->assertValid('./phpunit.xml', $content);
    }

    public function testFailsLoudOnASchemaViolation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('violates the phpunit config schema');

        new PhpUnitConfigValidator()->assertValid('./phpunit.xml', "<phpunit bogusAttribute=\"nonsense\" />\n");
    }

    public function testFailsLoudOnMalformedXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not well-formed XML');

        new PhpUnitConfigValidator()->assertValid('./phpunit.xml', "<phpunit>\n");
    }

    public function testFailsLoudWhenPhpUnitIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('install phpunit/phpunit');

        new PhpUnitConfigValidator(phpunitInstalled: false)->assertValid('./phpunit.xml', "<phpunit />\n");
    }

    public function testIgnoresAFileItDoesNotCoverEvenWithoutPhpUnit(): void
    {
        $this->expectNotToPerformAssertions();

        new PhpUnitConfigValidator(phpunitInstalled: false)->assertValid('./other.xml', 'anything');
    }
}
