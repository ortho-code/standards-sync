<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Core\Engine;

use AlleKnalle\StandardsSync\Core\Engine\TargetResolver;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\InMemoryFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** The classification edges of the dist convention; the policy itself is exercised through the pipeline in EngineTest. */
#[CoversClass(TargetResolver::class)]
final class TargetResolverTest extends TestCase
{
    public function testPrefersTheDistVariantInASubdirectory(): void
    {
        $resolved = $this->resolver([
            '/a/tools/phpunit.xml' => "local\n",
            '/a/tools/phpunit.xml.dist' => "committed\n",
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('tools/phpunit.xml', 'tools/phpunit.xml.dist'));

        self::assertSame('/a/tools/phpunit.xml.dist', $resolved->path()->value());
        self::assertSame('/a/tools/phpunit.xml', $resolved->shadowedBy()?->value());
    }

    public function testPrefersADistVariantWithAMiddleSegment(): void
    {
        $resolved = $this->resolver([
            '/a/psalm.xml' => "local\n",
            '/a/psalm.dist.xml' => "committed\n",
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('psalm.xml', 'psalm.dist.xml'));

        self::assertSame('/a/psalm.dist.xml', $resolved->path()->value());
    }

    public function testPrefersTheDistVariantInADotfileName(): void
    {
        $resolved = $this->resolver([
            '/a/.php-cs-fixer.php' => "local\n",
            '/a/.php-cs-fixer.dist.php' => "committed\n",
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('.php-cs-fixer.php', '.php-cs-fixer.dist.php'));

        self::assertSame('/a/.php-cs-fixer.dist.php', $resolved->path()->value());
    }

    public function testADistDirectoryDoesNotMakeAFileADistVariant(): void
    {
        // If the directory name counted, this would resolve with a preference instead of refusing two same-side files.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Both "/a/dist/a.conf" and "/a/etc/a.conf" exist for one target; remove all but one.');

        $this->resolver([
            '/a/dist/a.conf' => "one\n",
            '/a/etc/a.conf' => "two\n",
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('dist/a.conf', 'etc/a.conf'));
    }

    public function testDistInsideASegmentIsNotADistVariant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Both "/a/distribution.neon" and "/a/phpstan.neon" exist for one target; remove all but one.');

        $this->resolver([
            '/a/distribution.neon' => "one\n",
            '/a/phpstan.neon' => "two\n",
        ])->resolve(Path::fromString('/a'), FileTarget::fromStrings('distribution.neon', 'phpstan.neon'));
    }

    /** @param array<string, string> $files */
    private function resolver(array $files): TargetResolver
    {
        return new TargetResolver(new InMemoryFilesystem($files));
    }
}
