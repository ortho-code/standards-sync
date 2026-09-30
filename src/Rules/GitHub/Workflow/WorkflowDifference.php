<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

use Closure;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlEntry;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlItem;
use OrthoCode\StandardsSync\Formats\Yaml\Tree\YamlTree;
use OrthoCode\StandardsSync\Formats\Yaml\YamlFragment;
use OrthoCode\StandardsSync\Formats\Yaml\YamlTreeWriter;
use RuntimeException;

/**
 * One way a project's workflow falls short of the declared one: why, in words a maintainer reads, and the edit that resolves it.
 * A difference sync cannot edit away resolves by refusing, with what to write instead.
 */
final readonly class WorkflowDifference
{
    /** @param Closure(string): string $resolve */
    private function __construct(
        private string $explanation,
        private Closure $resolve,
    ) {}

    /** @param list<string|int> $mappingPath */
    public static function missingEntry(array $mappingPath, string $place, YamlTree $declared, YamlEntry $entry): self
    {
        $fragment = YamlFragment::fromEntry($declared, $entry);

        return new self(
            sprintf('It does not have "%s" yet.', self::within($place, $entry->key())),
            static fn(string $content): string => YamlTreeWriter::addEntry($content, $mappingPath, $fragment),
        );
    }

    /** @param list<string|int> $path */
    public static function changedScalar(array $path, string $place, string $actual, string $declared): self
    {
        return new self(
            sprintf('It has "%s" as %s where the standard declares %s.', $place, $actual, $declared),
            static fn(string $content): string => YamlTreeWriter::replaceScalar($content, $path, $declared),
        );
    }

    /**
     * @param non-empty-list<string|int> $path
     * @param YamlEntry $entry the declared entry whose value replaces the project's
     */
    public static function changedValue(array $path, string $place, YamlTree $declared, YamlEntry $entry): self
    {
        $fragment = YamlFragment::fromValue($declared, $entry);

        return new self(
            sprintf('Its "%s" differs from the one the standard declares.', $place),
            static fn(string $content): string => YamlTreeWriter::replaceValue($content, $path, $fragment),
        );
    }

    /** @param list<string|int> $path */
    public static function missingListItem(array $path, string $place, string $item): self
    {
        return new self(
            sprintf('Its "%s" does not list %s yet.', $place, $item),
            static fn(string $content): string => YamlTreeWriter::appendScalarItem($content, $path, $item),
        );
    }

    /**
     * @param list<string|int> $stepsPath
     * @param string|null $after the id of the declared step it goes after, null for a first step
     */
    public static function missingStep(array $stepsPath, int $index, string $job, YamlTree $declared, YamlItem $step, string $id, ?string $after): self
    {
        $fragment = YamlFragment::fromItem($declared, $step);

        return new self(
            sprintf('The job "%s" does not run the step "%s" yet; it goes %s.', $job, $id, $after === null ? 'first' : sprintf('after "%s"', $after)),
            static fn(string $content): string => YamlTreeWriter::insertItem($content, $stepsPath, $index, $fragment),
        );
    }

    /**
     * @param list<string|int> $stepPath
     * @param string $idSource the declared id as its source reads
     */
    public static function unidentifiedStep(array $stepPath, string $job, string $id, string $idSource): self
    {
        $fragment = YamlFragment::fromScalarEntry(WorkflowSyntax::KEY_ID, $idSource);

        return new self(
            sprintf('A step of the job "%s" is the declared step "%s" without its id, and gains it.', $job, $id),
            static fn(string $content): string => YamlTreeWriter::addEntry($content, $stepPath, $fragment),
        );
    }

    public static function reorderedStep(string $job, string $id, string $after): self
    {
        return self::refusal(sprintf(
            'The job "%s" runs the step "%s" before "%s", which the standard declares first; move "%s" after "%s" and sync again.',
            $job,
            $id,
            $after,
            $id,
            $after,
        ));
    }

    public static function unwritableShape(string $place, string $actual, string $declared): self
    {
        return self::refusal(sprintf('It has "%s" as %s where the standard declares %s; write it as %s and sync again.', $place, $actual, $declared, $declared));
    }

    public function explanation(): string
    {
        return $this->explanation;
    }

    public function resolved(string $content): string
    {
        return ($this->resolve)($content);
    }

    private static function refusal(string $explanation): self
    {
        return new self($explanation, static function (string $content) use ($explanation): never {
            throw new RuntimeException($explanation);
        });
    }

    private static function within(string $place, string $key): string
    {
        return $place === '' ? $key : $place . '.' . $key;
    }
}
