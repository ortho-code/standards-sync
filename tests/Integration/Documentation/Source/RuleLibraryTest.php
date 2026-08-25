<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Integration\Documentation\Source;

use StandardsSync\Documentation\Source\RuleLibrary;
use StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\StandardsSync\Integration\IntegrationTestCase;

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
            'Tests\StandardsSync\Integration\Documentation\Source\WorkspaceFixture',
        );
    }
}
