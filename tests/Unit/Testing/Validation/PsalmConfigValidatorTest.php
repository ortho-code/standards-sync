<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Testing\Validation;

use AlleKnalle\StandardsSync\Testing\Validation\PsalmConfigValidator;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PsalmConfigValidator::class)]
final class PsalmConfigValidatorTest extends TestCase
{
    public function testIgnoresAFileThatIsNoPsalmConfig(): void
    {
        $this->expectNotToPerformAssertions();

        new PsalmConfigValidator()->assertValid('./other.xml', "<foo><bar></foo>\n");
    }

    // The schema tier activates only where psalm is installed; this suite cannot host it (phpunit 13 conflict), so the validator must stay silent here.
    public function testStaysSilentWhenPsalmIsNotInstalled(): void
    {
        if (InstalledVersions::isInstalled('vimeo/psalm')) {
            self::markTestSkipped('psalm is installed in this suite; the no-op guard cannot be observed.');
        }

        $this->expectNotToPerformAssertions();

        new PsalmConfigValidator()->assertValid('./psalm.xml', "<psalm><bogus /></psalm>\n");
    }
}
