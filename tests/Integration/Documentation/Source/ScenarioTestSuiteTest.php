<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Integration\Documentation\Source;

use StandardsSync\Documentation\Source\ScenarioTestSuite;
use StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\StandardsSync\Integration\IntegrationTestCase;

/** The refusal is generated into a temp workspace: a committed *Test.php fixture would be scanned by phpunit itself. */
#[CoversClass(ScenarioTestSuite::class)]
final class ScenarioTestSuiteTest extends IntegrationTestCase
{
    public function testRefusesATestFileThatIsNotAScenarioTestCase(): void
    {
        $file = $this->writeToWorkspace('suite/BogusTest.php', FileContent::fromString(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Tests\StandardsSync\Integration\Documentation\Source\WorkspaceFixture;

            final class BogusTest
            {
            }
            PHP));
        require $file;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not resolve to a StandardsSync\Testing\ScenarioTestCase subclass');

        ScenarioTestSuite::fromDirectory(
            $this->workspace() . '/suite',
            'Tests\StandardsSync\Integration\Documentation\Source\WorkspaceFixture',
        );
    }
}
