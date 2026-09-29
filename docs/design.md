# Design

The engine's current design, in one place: what the pieces are and how they fit today.
The reasoning behind every statement here — the decisions, their dates, and the rejected alternatives — lives in [the decision record](history/rule-model.md); nothing on this page overrides it.

## The goal

Share coding-standards config across many repos from one package, keep it updated automatically (no manual copy-paste), and support the full range of config a project accumulates — `.editorconfig`, `.gitignore`, static-analysis configs, framework config, and more — each with its own format and mechanism.

## Positioning: floors, not copies

A copy *downgrades* a stricter project; an import ships a default the consumer can silently drop below.
A value-aware rule *enforces* a minimum: a project below the floor is raised on sync, a stricter project is never touched.

## Three tiers of sharing

- **Tier A — native import / extends.** The format can reference shared config (PHPStan `includes`, Rector `require`, renovate `extends`). Sharing is the real config in the package plus a one-line import in the repo; the content rides `composer update`.
- **Tier B — structured merge.** Parseable files with no import (`composer.json`, XML configs): rules own specific keys and leave the rest. Value-aware rules (floors, ceilings, pins) live here.
- **Tier C — opaque text.** No import, no structure to key on (`.editorconfig`, `.gitignore`): managed marker blocks.

Priority is A → B → C by value. There is deliberately **no universal parse-and-merge framework**; each rule does narrow, targeted parsing of its one concern (see [prior approaches](history/prior-approaches.md) for the attempt that sank on that weight).

## The `Rule` contract

A `Rule` targets one file and applies itself as a pure, idempotent content transform:

```php
interface Rule
{
    /** The file this rule transforms, as ordered path candidates. */
    public function target(): FileTarget;

    /**
     * Pure, idempotent transform from current content to desired content.
     * Null in: the file does not exist. Null out: the file should not exist.
     */
    public function apply(?string $content): ?string;

    /** One line stating what this rule enforces; feeds the drift report and the generated docs. */
    public function description(): string;
}
```

- **Drift is derived, never hand-written**: a rule drifts exactly when `apply(current) !== current`, so check and apply cannot disagree. Idempotency (`apply(apply(x)) === apply(x)`) is asserted generically over every rule's fixtures.
- **The symmetric null**: `apply(null)` returning null is abstention — no file, no opinion. Only rules that mean to create do so; whole-file deletion (string in, null out) is refused until a delete branch exists.
- **Rule identity is the class name** (FQCN in config and disable lists; short name plus target path in reports).
- Opt-in seams beside the base contract: `ExplainsDrift` (a dynamic "why" for drift that is not self-evident from the diff), `AppliesAtPath` (the resolved candidate for rules whose behaviour varies per filename, e.g. differing grammars under one target) and `ContributesToList` (a rule contributing entries to a list, whose contributions to one list merge before the fold and are recorded in [the lock](#the-lock)).

## `FileTarget` and resolution

A target is an ordered list of relative path candidates in the tool's own precedence (`phpstan.neon`, `phpstan.neon.dist`, `phpstan.dist.neon`).
Resolution happens at plan time:

- No candidate exists → the first candidate is the creation target.
- Exactly one exists → it is the sync target, whatever its name.
- One dist and one non-dist exist → **the dist variant wins**: the committed home takes the standard, and the passed-over local file is reported on every run, never silently.
- More than one candidate on the same side of the dist convention → plan-time refusal; a repo carrying two committed homes is confused, not a workflow.

Dist-ness derives from the filename (a dotted segment exactly `dist`), owned by `Core/Engine/TargetResolver`.

## How the engine runs rules

The engine groups rules by resolved target and folds each file's rules in declaration order over the content; rules never touch the filesystem.
Before that, contributions to one list (same class, target and list key) merge into one rule, standing at the first declaration's position.
One `Change` per file; `Engine::apply` is the only writer; `--check` computes the same plan and writes nothing, exiting non-zero on drift.
`ChangeKind` derives from the fold's endpoints (create, update, in sync).
The report attributes drift per rule (description plus `explain()` where implemented) and carries two note kinds: a shadowing note (a local file the tool reads in preference to the synced dist file) and an abstention note (a resolved file whose rules all had no opinion).

## The lock

Each root's `standards-sync.lock` records, per file and list, the entries its list contributions declared at the last sync, and is committed beside `standards-sync.php`.
The engine reads it before the root's files fold and plans it as one more file after them, so `Engine::apply` stays the only writer and a stale or missing lock is drift.
A root without contributions gets no lock, and a file that is absent keeps whatever the lock recorded for it, since nothing was enforced there to supersede it.
The engine owns the file end to end: files and list keys sort, so its rendering depends only on what was declared.

## Composition and layering

- Rules compose by declaration order; a later same-label block rule replaces an earlier one (last-wins), which is also how a child tier overrides a parent wholesale.
- Contributions to one list are the exception: they merge rather than replace, in declaration order, so a standard declared beside another adds to what that one declares.
- An org hierarchy composes with `include()`: the second tier's standard includes the base and adds or overrides rules. Tool imports layer additively — each tier registers its own entry, and the tools' own later-wins semantics deliver the override.
- Consumer-side disabling follows a ladder: an FQCN list (`withoutRule(X::class)`), then a predicate for full precision; overriding is `withoutRule()` plus `addRule(new X(...))` — rules stay immutable.

## The Authoring layer

An org package extends `Standard` (its one public class: rule declarations only) and reaches its distributed content through `Package` — templates under a single `templates/` directory, read via `read()` and referenced via `path()`.
`Package::fromClass()` locates the package through composer's install record, which is the only source that yields the portable `vendor/<name>` spelling under every install layout, symlinked path repos included; deviating layouts inject a `Package` explicitly.
In the org package's own repository it is the root package and references render bare.
The full guide: [authoring-org-packages.md](authoring-org-packages.md).

## Enforced comments

Value-writing rules take an opt-in `comment:`, written with the enforced value and enforced like it — a missing or edited comment is drift.
Unconfigured rules preserve a project's own trailing comment; a configured comment owns the spot.
One enforced line per comment; formats without an inline comment position (XML attributes) ship without, relying on `ExplainsDrift` and the catalog.

## Formats and validation

Format-editing machinery lives in `Formats/` (PHP fluent chains, neon, JSON, JSON5, XML, YAML), behind deptrac-enforced seams: rules depend only on the `Formats/` API, so a writer's backend can be swapped without consumers noticing — the standing re-assessment rule is in [conventions.md](conventions.md).
Synced output is parse-validated in tests per format, failing loud when a covering parser is missing rather than skipping.
Every shipped rule is documented with real before/after examples in the [generated rule catalog](rules/README.md), which is regenerated from the scenario suite and held fresh by a failing test — the catalog is the current-state view of the rule library.

## Architecture

The layering, the pipeline, and the invariants that hold all of the above together: [architecture.md](architecture.md).
