<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Source;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use RuntimeException;

/** The scenario test classes found under a suite directory, resolved through its PSR-4 namespace prefix. */
final readonly class ScenarioTestSuite
{
    private const string TEST_CLASS_SUFFIX = 'Test';

    /** @param list<class-string<ScenarioTestCase>> $testClasses */
    private function __construct(private array $testClasses)
    {
    }

    public static function fromDirectory(string $directory, string $namespacePrefix): self
    {
        $classes = [];
        foreach (Psr4Classes::fromDirectory($directory, $namespacePrefix)->all() as $class => $path) {
            if (!str_ends_with($class, self::TEST_CLASS_SUFFIX)) {
                continue;
            }
            // The scenario suite is the fixture catalog; a test file there that is not a ScenarioTestCase would silently thin the generated docs, so refuse loudly instead.
            if (!is_subclass_of($class, ScenarioTestCase::class)) {
                throw new RuntimeException(sprintf('"%s" does not resolve to a %s subclass ("%s").', $path, ScenarioTestCase::class, $class));
            }
            $classes[] = $class;
        }
        sort($classes);

        return new self($classes);
    }

    /** @return list<class-string<ScenarioTestCase>> */
    public function testClasses(): array
    {
        return $this->testClasses;
    }
}
