<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Testing\Validation;

use OrthoCode\StandardsSync\Testing\Validation\NeonValidator;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(NeonValidator::class)]
final class NeonValidatorTest extends TestCase
{
    public function testAcceptsValidNeon(): void
    {
        $this->expectNotToPerformAssertions();

        $content = FileContent::fromString(
            <<<'NEON'
                parameters:
                	level: 6
                NEON
        );

        new NeonValidator()->assertValid('./phpstan.neon', $content);
    }

    public function testFailsLoudOnBrokenNeon(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The synced ./phpstan.neon is not valid neon');

        new NeonValidator()->assertValid('./phpstan.neon', "parameters:\n\tlevel: [6\n");
    }

    public function testIgnoresAFileThatIsNotNeon(): void
    {
        $this->expectNotToPerformAssertions();

        new NeonValidator()->assertValid('./notes.txt', "parameters:\n\tlevel: [6\n");
    }

    public function testFailsLoudWhenTheParserIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('install nette/neon');

        new NeonValidator(parserInstalled: false)->assertValid('./phpstan.neon', "parameters:\n\tlevel: 6\n");
    }

    public function testIgnoresAFileItDoesNotCoverEvenWithoutTheParser(): void
    {
        $this->expectNotToPerformAssertions();

        new NeonValidator(parserInstalled: false)->assertValid('./notes.txt', 'anything');
    }
}
