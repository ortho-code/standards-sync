<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Authoring;

use OrthoCode\StandardsSync\Authoring\Package;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Package::class)]
final class PackageTest extends TestCase
{
    public function testPathRendersTheConsumerRelativeReferenceIntoTemplates(): void
    {
        $package = new Package('/anywhere', 'vendor/acme/standards');

        self::assertSame('vendor/acme/standards/templates/ecs.php', $package->path('ecs.php'));
    }

    public function testPathKeepsSubdirectories(): void
    {
        $package = new Package('/anywhere', 'vendor/acme/standards');

        self::assertSame('vendor/acme/standards/templates/phpstan/strict.neon', $package->path('phpstan/strict.neon'));
    }

    public function testPathFromAPackageAtTheProjectRootIsBare(): void
    {
        $package = new Package('/anywhere', '');

        self::assertSame('templates/ecs.php', $package->path('ecs.php'));
    }

    public function testRejectsAnAbsoluteInstallLocation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Package('/anywhere', '/vendor/acme/standards');
    }

    public function testRejectsAnAbsoluteDistributedFile(): void
    {
        $package = new Package('/anywhere', 'vendor/acme/standards');

        $this->expectException(InvalidArgumentException::class);

        $package->path('/etc/ecs.php');
    }
}
