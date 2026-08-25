<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Documentation\Source;

use AlleKnalle\StandardsSync\Documentation\Source\RuleLibrary;
use AlleKnalle\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\AlleKnalle\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(RuleLibrary::class)]
final class RuleLibraryTest extends IntegrationTestCase
{
    public function testRefusesAFileThatDoesNotResolveToALoadableType(): void
    {
        $this->writeToWorkspace('library/Ghost.php', FileContent::fromString(<<<'PHP'
            <?php

            declare(strict_types=1);

            // The type this path promises is never defined.
            PHP));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not resolve to a loadable type');

        RuleLibrary::fromDirectory(
            $this->workspace() . '/library',
            'Tests\AlleKnalle\StandardsSync\Integration\Documentation\Source\WorkspaceFixture',
        );
    }
}
