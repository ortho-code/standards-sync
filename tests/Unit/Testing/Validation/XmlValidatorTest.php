<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Testing\Validation;

use StandardsSync\Testing\Validation\XmlValidator;
use StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(XmlValidator::class)]
final class XmlValidatorTest extends TestCase
{
    public function testAcceptsWellFormedXml(): void
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

        new XmlValidator()->assertValid('./psalm.xml', $content);
    }

    public function testFailsLoudOnMalformedXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The synced ./broken.xml is not well-formed XML');

        new XmlValidator()->assertValid('./broken.xml', "<foo><bar></foo>\n");
    }

    public function testIgnoresAFileThatIsNotXml(): void
    {
        $this->expectNotToPerformAssertions();

        new XmlValidator()->assertValid('./notes.txt', "<foo><bar></foo>\n");
    }
}
