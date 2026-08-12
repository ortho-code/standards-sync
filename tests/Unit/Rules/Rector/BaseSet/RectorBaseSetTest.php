<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\Rector\BaseSet;

use AlleKnalle\StandardsSync\Rules\Rector\BaseSet\RectorBaseSet;
use AlleKnalle\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RectorBaseSet::class)]
final class RectorBaseSetTest extends TestCase
{
    private const string IMPORT = 'vendor/acme/standards/config/rector.php';

    #[DataProvider('importScenarios')]
    public function testAppliesTheImport(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('importScenarios')]
    public function testApplyIsIdempotent(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function importScenarios(): iterable
    {
        yield 'a project without a config gets one created, holding just the import' => [
            null,
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;

                    return RectorConfig::configure()
                        ->withSets([
                            __DIR__ . '/vendor/acme/standards/config/rector.php',
                        ]);
                    PHP
            ),
        ];

        yield 'an existing withSets array gains the entry after its last entry' => [
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;
                    use Rector\Set\ValueObject\SetList;

                    return RectorConfig::configure()
                        ->withPaths([
                            __DIR__ . '/src',
                        ])
                        ->withSets([
                            SetList::DEAD_CODE,
                        ]);
                    PHP
            ),
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;
                    use Rector\Set\ValueObject\SetList;

                    return RectorConfig::configure()
                        ->withPaths([
                            __DIR__ . '/src',
                        ])
                        ->withSets([
                            SetList::DEAD_CODE,
                            __DIR__ . '/vendor/acme/standards/config/rector.php',
                        ]);
                    PHP
            ),
        ];

        yield 'a chain without withSets gains the call at its end' => [
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;

                    return RectorConfig::configure()
                        ->withPaths([
                            __DIR__ . '/src',
                        ]);
                    PHP
            ),
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;

                    return RectorConfig::configure()
                        ->withPaths([
                            __DIR__ . '/src',
                        ])
                        ->withSets([
                            __DIR__ . '/vendor/acme/standards/config/rector.php',
                        ]);
                    PHP
            ),
        ];

        yield 'an already-registered import leaves the config unchanged' => [
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;

                    return RectorConfig::configure()
                        ->withSets([
                            __DIR__ . '/vendor/acme/standards/config/rector.php',
                        ]);
                    PHP
            ),
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;

                    return RectorConfig::configure()
                        ->withSets([
                            __DIR__ . '/vendor/acme/standards/config/rector.php',
                        ]);
                    PHP
            ),
        ];

        yield 'the inserted entry copies the indentation of a two-space file' => [
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;
                    use Rector\Set\ValueObject\SetList;

                    return RectorConfig::configure()
                      ->withSets([
                        SetList::DEAD_CODE,
                      ]);
                    PHP
            ),
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;
                    use Rector\Set\ValueObject\SetList;

                    return RectorConfig::configure()
                      ->withSets([
                        SetList::DEAD_CODE,
                        __DIR__ . '/vendor/acme/standards/config/rector.php',
                      ]);
                    PHP
            ),
        ];
    }

    public function testRefusesACallableStyleConfig(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fluent form');

        $this->rule()->apply(FileContent::fromString(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Rector\Config\RectorConfig;

                return static function (RectorConfig $rectorConfig): void {
                    $rectorConfig->sets([__DIR__ . '/config/sets.php']);
                };
                PHP
        ));
    }

    public function testRefusesASingleLineSetsArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('one entry per line');

        $this->rule()->apply(FileContent::fromString("return RectorConfig::configure()->withSets([SetList::DEAD_CODE]);"));
    }

    public function testRejectsAnEmptySetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RectorBaseSet(set: ' ');
    }

    // The author passes data, not PHP: expression text would be written verbatim into every consumer config.
    public function testRejectsAnExpressionAsTheSetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('relative path');

        new RectorBaseSet(set: "__DIR__ . '/vendor/acme/standards/config/rector.php'");
    }

    public function testRejectsAnAbsoluteSetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('relative');

        new RectorBaseSet(set: '/vendor/acme/standards/config/rector.php');
    }

    public function testTargetsTheRectorConfigCandidatesInLookupOrder(): void
    {
        self::assertSame('rector.php | rector.dist.php', $this->rule()->target()->toString());
    }

    public function testDescribesAndExplainsTheImport(): void
    {
        $rule = $this->rule();

        self::assertSame('Ensures the Rector config registers vendor/acme/standards/config/rector.php in withSets().', $rule->description());
        self::assertSame('There is no Rector config yet; one is created registering vendor/acme/standards/config/rector.php.', $rule->explain(null));
        self::assertSame('The Rector config does not register vendor/acme/standards/config/rector.php in withSets().', $rule->explain(FileContent::fromString('return RectorConfig::configure();')));
    }

    private function rule(): RectorBaseSet
    {
        return new RectorBaseSet(set: self::IMPORT);
    }
}
