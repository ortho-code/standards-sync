<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Presentation\Cli;

use AlleKnalle\StandardsSync\Core\Plan\ChangeKind;
use AlleKnalle\StandardsSync\Core\Plan\Plan;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Renders a Plan for humans: one line per drifting file plus a summary, or a clean-state notice. */
final readonly class DriftReport
{
    public function render(Plan $plan, SymfonyStyle $style): void
    {
        $drift = $plan->drift();

        if ($drift === []) {
            $style->success('All managed files are in sync.');

            return;
        }

        $lines = [];
        foreach ($drift as $change) {
            $lines[] = sprintf('%s %s', $this->verb($change->kind()), $change->path()->value());
        }

        $style->listing($lines);
        $style->warning(sprintf('%d file(s) drift from the managed standard.', count($drift)));
    }

    private function verb(ChangeKind $kind): string
    {
        return match ($kind) {
            ChangeKind::Create => 'CREATE',
            ChangeKind::Update => 'UPDATE',
            ChangeKind::InSync => 'IN SYNC',
        };
    }
}
