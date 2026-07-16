<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Presentation\Cli;

use AlleKnalle\StandardsSync\Core\Plan\Change;
use AlleKnalle\StandardsSync\Core\Plan\ChangeKind;
use AlleKnalle\StandardsSync\Core\Plan\Plan;
use AlleKnalle\StandardsSync\Core\Rule\ExplainsDrift;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use ReflectionClass;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Renders a Plan for humans: each drifting file with the rules that drifted, plus a summary, or a clean-state notice. */
final readonly class DriftReport
{
    public function render(Plan $plan, SymfonyStyle $style): void
    {
        $drift = $plan->drift();

        if ($drift === []) {
            $style->success('All managed files are in sync.');

            return;
        }

        foreach ($drift as $change) {
            $style->text($this->fileLine($change));
            foreach ($this->ruleLines($change) as $line) {
                $style->text($line);
            }
        }

        $style->warning(sprintf('%d file(s) drift from the managed standard.', count($drift)));
    }

    private function fileLine(Change $change): string
    {
        return sprintf(' %s %s', $this->verb($change->kind()), $change->path()->value());
    }

    /**
     * One line per distinct drifting rule; indistinguishable instances (same class, description and explanation) collapse into one line with a count.
     *
     * @return list<string>
     */
    private function ruleLines(Change $change): array
    {
        $counts = [];
        foreach ($change->driftingApplications() as $application) {
            $text = $this->ruleText($application->rule(), $application->before());
            $counts[$text] = ($counts[$text] ?? 0) + 1;
        }

        $lines = [];
        foreach ($counts as $text => $count) {
            $lines[] = sprintf('   - %s%s', $count > 1 ? sprintf('(×%d) ', $count) : '', $text);
        }

        return $lines;
    }

    private function ruleText(Rule $rule, ?string $before): string
    {
        $text = sprintf('%s: %s', new ReflectionClass($rule)->getShortName(), $rule->description());
        if ($rule instanceof ExplainsDrift) {
            $text .= ' ' . $rule->explain($before);
        }

        return $text;
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
