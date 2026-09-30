<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Testing\Validation;

use OrthoCode\StandardsSync\Testing\FileContent;
use OrthoCode\StandardsSync\Testing\Validation\PsalmConfigValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PsalmConfigValidator::class)]
final class PsalmConfigValidatorTest extends TestCase
{
    public function testIgnoresAFileThatIsNoPsalmConfig(): void
    {
        $this->expectNotToPerformAssertions();

        new PsalmConfigValidator()->assertValid('./other.xml', FileContent::fromString('<foo><bar></foo>'));
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
                XML,
        );

        new PsalmConfigValidator()->assertValid('./psalm.xml', $content);
    }

    public function testFailsLoudOnASchemaViolation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('violates the psalm config schema');

        new PsalmConfigValidator()->assertValid('./psalm.xml', FileContent::fromString('<psalm errorLevel="4"><bogus /></psalm>'));
    }

    public function testFailsLoudWhenPsalmIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('install vimeo/psalm');

        new PsalmConfigValidator(psalmInstalled: false)->assertValid('./psalm.xml', FileContent::fromString('<psalm errorLevel="4" />'));
    }

    public function testIgnoresAFileItDoesNotCoverEvenWithoutPsalm(): void
    {
        $this->expectNotToPerformAssertions();

        new PsalmConfigValidator(psalmInstalled: false)->assertValid('./other.xml', 'anything');
    }
}
