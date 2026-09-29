<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Rector\BaseSet;

use OrthoCode\StandardsSync\Rules\Ecs\BaseSet\EcsBaseSet;
use OrthoCode\StandardsSync\Rules\Rector\BaseSet\RectorBaseSet;
use OrthoCode\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use LogicException;
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                    PHP,
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
                PHP,
        ));
    }

    public function testRefusesASingleLineSetsArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('one entry per line');

        $this->rule()->apply(FileContent::fromString('return RectorConfig::configure()->withSets([SetList::DEAD_CODE]);'));
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

        new RectorBaseSet(set: '__DIR__ . \'/vendor/acme/standards/config/rector.php\'');
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

    public function testMergedDeclarationsRegisterEverySetOnceInDeclarationOrder(): void
    {
        $rule = $this->rule()
            ->withMerged(new RectorBaseSet(set: 'vendor/acme/framework/config/rector.php'))
            ->withMerged($this->rule());

        self::assertSame(['__DIR__ . \'/vendor/acme/standards/config/rector.php\'', '__DIR__ . \'/vendor/acme/framework/config/rector.php\''], $rule->entries());
        self::assertSame('Ensures the Rector config registers vendor/acme/standards/config/rector.php, vendor/acme/framework/config/rector.php in withSets().', $rule->description());
        self::assertSame(
            FileContent::fromString(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    use Rector\Config\RectorConfig;

                    return RectorConfig::configure()
                        ->withSets([
                            __DIR__ . '/vendor/acme/standards/config/rector.php',
                            __DIR__ . '/vendor/acme/framework/config/rector.php',
                        ]);
                    PHP,
            ),
            $rule->apply(null),
        );
    }

    public function testRefusesToMergeAnotherRule(): void
    {
        $this->expectException(LogicException::class);

        $this->rule()->withMerged(new EcsBaseSet(set: 'vendor/acme/standards/config/ecs.php'));
    }

    public function testAMissingSetTakesTheFirstRetiredSetsPlaceAndTheOthersAreRetracted(): void
    {
        $rule = $this->rule()->withRetired(['__DIR__ . \'/vendor/acme/standards/rector.php\'', '__DIR__ . \'/vendor/acme/standards/strict.php\'']);
        $current = FileContent::fromString(
            <<<'PHP'
                return RectorConfig::configure()
                    ->withSets([
                        __DIR__ . '/vendor/acme/standards/rector.php', // the org set
                        __DIR__ . '/rector-local.php',
                        __DIR__ . '/vendor/acme/standards/strict.php',
                    ]);
                PHP,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'PHP'
                    return RectorConfig::configure()
                        ->withSets([
                            __DIR__ . '/vendor/acme/standards/config/rector.php', // the org set
                            __DIR__ . '/rector-local.php',
                        ]);
                    PHP,
            ),
            $rule->apply($current),
        );
        self::assertSame(
            'The Rector config does not register vendor/acme/standards/config/rector.php in withSets(). It stops registering __DIR__ . \'/vendor/acme/standards/rector.php\', __DIR__ . \'/vendor/acme/standards/strict.php\', which no standard declares any more.',
            $rule->explain($current),
        );
    }

    private function rule(): RectorBaseSet
    {
        return new RectorBaseSet(set: self::IMPORT);
    }
}
