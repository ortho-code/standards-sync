<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use LogicException;
use OrthoCode\StandardsSync\Core\Rule\ContributesToList;
use OrthoCode\StandardsSync\Core\Rule\ExplainsDrift;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Rule\Rule;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlLines;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\MarkerGrammar;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\MarkerSyntax;

/**
 * Keeps a declared GitHub Actions workflow in a project's workflow file: every key, job and step it declares is there, and whatever the project adds stays.
 * A missing key, job or step is added — a step after the one declared before it — and a declared value the project changed is written back; the file's own formatting and comments stay as they are.
 * Every declared step carries an id; a project's step without one that already holds a declared step is taken as that step and gains its id.
 * A key, job, step or list item a standard declared at an earlier sync and declares no longer is taken out, with whatever the project added inside it.
 * An absent file is written as the workflow is declared, comments included.
 */
final readonly class GitHubWorkflow implements Rule, ContributesToList, ExplainsDrift
{
    /** The one list a workflow contributes: its declared nodes, as pointers. */
    private const string LIST_KEY = 'workflow';

    private DeclaredWorkflow $declared;

    /** @var list<string> the pointers an earlier sync recorded and nothing declares now */
    private array $retired;

    /**
     * @param string $workflow the workflow as a standard ships it
     * @param Label|null $replacesBlock the managed block this rule takes over from a standard that shipped the workflow as one: its two marker lines go on the first sync, and its content stays
     */
    public function __construct(
        private FileTarget $target,
        string $workflow,
        private ?Label $replacesBlock = null,
    ) {
        $this->declared = DeclaredWorkflow::fromString($workflow);
        $this->retired = [];
    }

    #[\Override]
    public function target(): FileTarget
    {
        return $this->target;
    }

    #[\Override]
    public function listKey(): string
    {
        return self::LIST_KEY;
    }

    #[\Override]
    public function entries(): array
    {
        return $this->declared->pointers();
    }

    #[\Override]
    public function withMerged(ContributesToList $later): static
    {
        throw new LogicException(sprintf('Two declarations of the workflow %s cannot be combined yet.', $this->target->toString()));
    }

    #[\Override]
    public function withRetired(array $retired): static
    {
        /** @var static $retiring psalm types clone-with as a plain object */
        $retiring = clone($this, [
            'retired' => $retired,
        ]);

        return $retiring;
    }

    #[\Override]
    public function apply(?string $content): ?string
    {
        return $content === null ? $this->declared->source() : $this->synced($content)[0];
    }

    #[\Override]
    public function explain(?string $content): string
    {
        if ($content === null) {
            return 'The workflow does not exist yet, and is written as the standard declares it.';
        }
        $reasons = $this->synced($content)[1];

        return $reasons === [] ? 'The workflow differs from the one the standard declares.' : implode(' ', $reasons);
    }

    #[\Override]
    public function description(): string
    {
        return sprintf('Keeps the declared workflow in %s, beside any keys, jobs and steps the project adds.', $this->target->toString());
    }

    /**
     * The content once it holds the declared workflow, and why each edit on the way was made.
     * Each edit resolves the first place the content falls short, so every declared node needs at most a couple of them; running out of passes is an engine bug, never a project's.
     *
     * @return array{string, list<string>}
     */
    private function synced(string $content): array
    {
        [$content, $reasons] = $this->withoutReplacedMarkers($content);
        $retired = array_values(array_filter(array_map(WorkflowPointer::fromString(...), $this->retired)));
        $passes = 2 * $this->declared->nodeCount() + count($retired) + 1;
        for ($pass = 0; $pass < $passes; $pass++) {
            $difference = WorkflowContainment::firstDifference($this->declared, YamlTree::fromString($content), $retired);
            if (!$difference instanceof WorkflowDifference) {
                return [$content, $reasons];
            }
            $reasons[] = $difference->explanation();
            $content = $difference->resolved($content);
        }

        throw new LogicException(sprintf('Syncing %s did not converge; this is an engine bug.', $this->target->toString()));
    }

    /**
     * The content without the replaced block's marker lines, and a reason when it had any.
     *
     * @return array{string, list<string>}
     */
    private function withoutReplacedMarkers(string $content): array
    {
        if (!$this->replacesBlock instanceof Label) {
            return [$content, []];
        }

        // A workflow is YAML, whose comments open with a hash.
        $grammar = new MarkerGrammar(MarkerSyntax::Hash, $this->replacesBlock);
        $lines = YamlLines::fromString($content);
        $markers = array_values(array_filter(
            range(0, max(0, $lines->count() - 1)),
            static fn(int $line): bool => $line < $lines->count() && in_array(rtrim($lines->line($line)), [$grammar->open(), $grammar->close()], true),
        ));
        if ($markers === []) {
            return [$content, []];
        }
        foreach (array_reverse($markers) as $line) {
            $lines = $lines->withRemoved($line, $line + 1);
        }

        return [$lines->toString(), [sprintf('It still carries the markers of the managed block "%s", which this rule takes over.', $this->replacesBlock->value())]];
    }
}
