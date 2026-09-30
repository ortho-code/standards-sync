<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowPointer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkflowPointer::class)]
final class WorkflowPointerTest extends TestCase
{
    public function testEscapesASlashAndATildeInASegment(): void
    {
        self::assertSame('/on/push/branches/release~1**/~0x', WorkflowPointer::fromSegments(['on', 'push', 'branches', 'release/**', '~x'])->toString());
    }

    public function testReadsBackWhatItWrites(): void
    {
        $pointer = WorkflowPointer::fromString('/on/push/branches/release~1**');

        self::assertNotNull($pointer);
        self::assertSame(['on', 'push', 'branches', 'release/**'], $pointer->segments());
        self::assertSame(4, $pointer->depth());
        self::assertSame('/on/push/branches', $pointer->parent()?->toString());
    }

    public function testTakesNoTextForAPointerThatIsNotOne(): void
    {
        self::assertNull(WorkflowPointer::fromString('jobs/checks'));
        self::assertNull(WorkflowPointer::fromString('/'));
        self::assertNull(WorkflowPointer::fromString(''));
    }

    public function testATopLevelKeyHasNoParent(): void
    {
        self::assertNull(WorkflowPointer::fromSegments(['jobs'])->parent());
    }

    public function testWritesAListItemAsItsText(): void
    {
        self::assertSame(['main', '8', 'true'], [WorkflowPointer::segmentOf('main'), WorkflowPointer::segmentOf(8), WorkflowPointer::segmentOf(true)]);
    }
}
