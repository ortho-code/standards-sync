<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Infrastructure\Filesystem;

use OrthoCode\StandardsSync\Infrastructure\Filesystem\DirectoryListing;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\OrthoCode\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(DirectoryListing::class)]
final class DirectoryListingTest extends IntegrationTestCase
{
    public function testListsNestedFilesAsSortedRelativePaths(): void
    {
        $this->writeToWorkspace('tree/b.txt', FileContent::fromString('b'));
        $this->writeToWorkspace('tree/a/deep.txt', FileContent::fromString('deep'));
        $this->writeToWorkspace('tree/a.txt', FileContent::fromString('a'));

        self::assertSame(
            ['a.txt', 'a/deep.txt', 'b.txt'],
            DirectoryListing::fromDirectory($this->workspace() . '/tree')->relativePaths(),
        );
    }

    public function testAnAbsentDirectoryListsAsEmpty(): void
    {
        self::assertSame([], DirectoryListing::fromDirectory($this->workspace() . '/missing')->relativePaths());
    }
}
