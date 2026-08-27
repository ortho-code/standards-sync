<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Authoring;

use OrthoCode\StandardsSync\Authoring\Package;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystemComponent;
use Tests\OrthoCode\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(Package::class)]
final class PackageTest extends IntegrationTestCase
{
    public function testReadsADistributedFile(): void
    {
        $this->writeToWorkspace('templates/.editorconfig', FileContent::fromString('root = true'));
        $package = new Package($this->workspace(), 'vendor/acme/standards');

        self::assertSame(FileContent::fromString('root = true'), $package->read('.editorconfig'));
    }

    public function testThrowsWhenTheDistributedFileIsMissing(): void
    {
        $package = new Package($this->workspace(), 'vendor/acme/standards');

        $this->expectException(IOException::class);

        $package->read('missing');
    }

    public function testFromClassLocatesAVendorInstalledPackage(): void
    {
        // Symfony's Filesystem class stands in for an org package: the nearest composer.json above it names symfony/filesystem, which composer's record places under vendor/.
        $package = Package::fromClass(SymfonyFilesystemComponent::class);

        self::assertSame('vendor/symfony/filesystem/templates/ecs.php', $package->path('ecs.php'));
    }

    public function testFromClassLocatesTheRootPackageAtTheProjectRoot(): void
    {
        // This test class belongs to the engine itself, which composer records as the root package, installed at the project root.
        $package = Package::fromClass(self::class);

        self::assertSame('templates/ecs.php', $package->path('ecs.php'));
    }
}
