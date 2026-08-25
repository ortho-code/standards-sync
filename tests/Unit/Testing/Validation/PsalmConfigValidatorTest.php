<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Testing\Validation;

use StandardsSync\Testing\FileContent;
use StandardsSync\Testing\Validation\PsalmConfigValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PsalmConfigValidator::class)]
final class PsalmConfigValidatorTest extends TestCase
{
    public function testIgnoresAFileThatIsNoPsalmConfig(): void
    {
        $this->expectNotToPerformAssertions();

        new PsalmConfigValidator()->assertValid('./other.xml', "<foo><bar></foo>\n");
    }

    public function testAcceptsAConfigTheSchemaAllows(): void
    {
        $this->expectNotToPerformAssertions();

        $content = FileContent::fromString(
            <<<'XML'
                <?xml version="1.0"?>
                <psalm errorLevel="4">
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML
        );

        new PsalmConfigValidator()->assertValid('./psalm.xml', $content);
    }

    public function testFailsLoudOnASchemaViolation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('violates the psalm config schema');

        new PsalmConfigValidator()->assertValid('./psalm.xml', "<psalm errorLevel=\"4\"><bogus /></psalm>\n");
    }

    public function testFailsLoudWhenPsalmIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('install vimeo/psalm');

        new PsalmConfigValidator(psalmInstalled: false)->assertValid('./psalm.xml', "<psalm errorLevel=\"4\" />\n");
    }

    public function testIgnoresAFileItDoesNotCoverEvenWithoutPsalm(): void
    {
        $this->expectNotToPerformAssertions();

        new PsalmConfigValidator(psalmInstalled: false)->assertValid('./other.xml', 'anything');
    }
}
