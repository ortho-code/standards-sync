<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Presentation\Cli\Output;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Plan\Abstention;
use OrthoCode\StandardsSync\Core\Plan\Change;
use OrthoCode\StandardsSync\Core\Plan\ChangeKind;
use OrthoCode\StandardsSync\Core\Plan\Plan;
use OrthoCode\StandardsSync\Core\Plan\RuleApplication;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use ReflectionClass;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Renders a Plan for humans: the plan's standing notes, then each drifting file with the rules that drifted, plus a summary, or a clean-state notice. */
final readonly class DriftReport
{
    private const string NOTE_LEAD = ' NOTE ';

    public function render(Plan $plan, SymfonyStyle $style): void
    {
        foreach ($this->notes($plan) as $note) {
            $style->text($note);
        }

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

    /**
     * Standing facts the plan discovered, told on every run — in-sync files included — so they never pass silently.
     *
     * @return list<string>
     */
    private function notes(Plan $plan): array
    {
        $notes = [];
        foreach ($plan->changes() as $change) {
            $note = $this->shadowingNote($change);
            if ($note !== null) {
                $notes[] = $note;
            }
        }

        foreach ($plan->abstentions() as $abstention) {
            $notes[] = $this->abstentionNote($abstention);
        }

        return $notes;
    }

    private function shadowingNote(Change $change): ?string
    {
        $shadowedBy = $change->shadowedBy();
        if (!$shadowedBy instanceof Path) {
            return null;
        }

        return sprintf(self::NOTE_LEAD . '%s exists and replaces %s for tool runs; the standard syncs to the dist file.', $shadowedBy->value(), $change->path()->value());
    }

    private function abstentionNote(Abstention $abstention): string
    {
        return sprintf(self::NOTE_LEAD . '%s does not exist; nothing was enforced there (%s).', $abstention->path()->value(), implode(', ', $this->ruleNames($abstention->rules())));
    }

    /**
     * The rules named once each, with a count where a rule stood down several times over.
     *
     * @param non-empty-list<Rule> $rules
     * @return list<string>
     */
    private function ruleNames(array $rules): array
    {
        $counts = $this->countOccurrences(array_map(
            static fn(Rule $rule): string => new ReflectionClass($rule)->getShortName(),
            $rules,
        ));

        return array_map(
            static fn(string $name, int $count): string => $count > 1 ? sprintf('%s ×%d', $name, $count) : $name,
            array_keys($counts),
            $counts,
        );
    }

    /**
     * How often each label occurs, in first-seen order; the two renderings above place the multiplier differently.
     *
     * @param list<string> $labels
     * @return array<string, int>
     */
    private function countOccurrences(array $labels): array
    {
        $counts = [];
        foreach ($labels as $label) {
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $counts;
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
        $counts = $this->countOccurrences(array_map(
            fn(RuleApplication $application): string => $this->ruleText($application->rule(), $application->before()),
            $change->driftingApplications(),
        ));

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
