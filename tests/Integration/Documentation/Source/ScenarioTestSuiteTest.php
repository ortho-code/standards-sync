<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Documentation\Source;

use AlleKnalle\StandardsSync\Documentation\Source\ScenarioTestSuite;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\AlleKnalle\StandardsSync\Integration\IntegrationTestCase;

/** The refusal is generated into a temp workspace: a committed *Test.php fixture would be scanned by phpunit itself. */
#[CoversClass(ScenarioTestSuite::class)]
final class ScenarioTestSuiteTest extends IntegrationTestCase
{
    public function testRefusesATestFileThatIsNotAScenarioTestCase(): void
    {
        $file = $this->writeToWorkspace('suite/BogusTest.php', FileContent::fromString(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Tests\AlleKnalle\StandardsSync\Integration\Documentation\Source\WorkspaceFixture;

            final class BogusTest
            {
            }
            PHP));
        require $file;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not resolve to a AlleKnalle\StandardsSync\Testing\ScenarioTestCase subclass');

        ScenarioTestSuite::fromDirectory(
            $this->workspace() . '/suite',
            'Tests\AlleKnalle\StandardsSync\Integration\Documentation\Source\WorkspaceFixture',
        );
    }
}
