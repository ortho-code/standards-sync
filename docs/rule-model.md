# Direction: a rule-based model

**Status:** direction agreed, not yet built — the current code is the block-model baseline it builds on. The pre-rename history (baseline and earlier takes) is preserved in the private predecessor repository.

## The goal

Share coding-standards config across many repos from one package, keep it updated automatically (no manual copy-paste), and support the full range of config a project accumulates — `.editorconfig`, `.gitignore`, static-analysis configs (PHPStan / Rector / …), framework config, and more — each with its own format and mechanism.

## Three tiers of sharing (by what the format allows)

- **Tier A — native import / extends.** The format can reference shared config (PHPStan `includes`, Rector `require`, ESLint import, tsconfig `extends`, framework config imports, composer-required PHP config). Sharing = ship the real config in the package + a one-line import in the repo; it rides `composer update` with no markers, no sync step, no drift. Biggest surface, cheapest mechanism.
- **Tier B — structured merge.** Parseable files with no import (`composer.json` / `package.json` scripts, XML configs): own specific keys, leave the rest.
- **Tier C — opaque text.** No import, no structure to key on (`.editorconfig`, `.gitignore`): managed marker blocks or line-level rules. Smallest, lowest-velocity tier.

Priority is A → B → C by value. The first build targeted C (managed blocks); the direction below unifies all three under one abstraction.

## The `Rule` abstraction

A `Rule` is the unifying primitive. It targets one file and **applies** itself as a pure content transform — idempotent, a no-op when already satisfied. Drift (`--check`) is **derived**, not hand-written: a rule is satisfied exactly when applying it changes nothing (see the pre-R0 decisions below). Rules compose by folding over a file's content one after another, with a single final write (the existing pure pipeline is kept).

Rule *types* map onto the tiers:

- **`FullFileRule` / managed-block rule** — places a template as a managed marker block. This is the current block engine, reframed as one rule type; it handles Tier C today.
- **`ImportRule`** — ensures a one-line import / require / extends is present (Tier A).
- **`KeyRule` / structured rules** — read / check / set a specific key in a parseable file (Tier B). These are **value-aware**, not blind sets: e.g. a *floor* — "PHPStan level ≥ 6: raise it if lower, leave it if already higher" (never weakens a stricter project) — plus list-unions, ceilings, enforce-presence, enforce-absence. This is where rules beat both a dumb copy (which would *downgrade* a level-9 project) and a plain import (which ships a default the consumer can silently drop below — a floor rule *enforces* the minimum). Each such rule does **narrow, targeted** parsing of its one key with existing format libraries; there is **no universal parse-and-merge framework** (that was the weight that sank the prior attempt — see [prior-approaches.md](prior-approaches.md)).

New rule types — and eventually community-contributed ones, as with the Rector / ECS rule libraries — extend the set without touching the pipeline.

Value-aware rules are the real payoff of Tier B and a strong argument for the whole direction; "raise PHPStan level only if lower" is a good first Tier B target once the abstraction exists.

**Positioning — "floors, not copies" (2026-07-15).** This is the product pitch and the README's opening example once docs land. It also explains in one line why the alternatives fall short: a copy *downgrades* a stricter project, an import ships a default the consumer can silently drop below — a floor rule *enforces* the minimum.

## Prior art (studied)

Three mature PHP tools converge on the same decomposition, deliberately split across separate seams rather than one fat interface:

- **Rector** — `RectorInterface` = `getNodeTypes()` (what to act on) + `refactor()` (act, or return `null` for "no change"). Configurability is a *separate* `ConfigurableRectorInterface::configure(array)`. The description + a mandatory before/after code sample live on yet another interface. Ordering: none — it re-runs to a fixpoint, so rules must be idempotent.
- **PHP-CS-Fixer** — `FixerInterface` = `isCandidate()` / `supports()` (cheap gate) + `fix()` (mutate in place) + `getDefinition()` (mandatory doc + sample) + `getPriority()`. Config via a *separate* `ConfigurableFixerInterface` with a self-describing, validated schema. Ordering: a single global integer priority — its known pain point.
- **PHP_CodeSniffer** — `Sniff` = `register()` (token types) + `process()` (report through the file, don't return findings). Config via plain public properties set by reflection from `ruleset.xml`. Ordering: none — analyzers don't interfere.

Convergent lessons for our `Rule`:

1. **Keep the base contract tiny** — target + `check` + `apply`. Config and docs go on separate seams, not the base.
2. **Separate `check` from `apply`** — `check` gives drift / `--check`; `apply` is idempotent and no-ops when already satisfied. *(Superseded 2026-07-15: we derive check from apply instead of hand-writing it — see the pre-R0 decisions below. The lesson's substance, apply no-oping when satisfied, stands.)*
3. **Config is opt-in** (`ConfigurableRule`), passed whole (replace-not-patch), defaults owned by the rule, and **typed / validated** (a per-rule immutable value object) rather than an untyped array.
4. **Each rule carries a description + a before/after sample**, which doubles as its test fixture.

## Decided 2026-07-15 (pre-R0) — contract, identity, absence

Settled in discussion ahead of R0; R0 turns these into the actual interface, tested against the three probes.

### No hand-written `check()` — drift derives from a pure `apply`

`Rule` has no `check()` method. The rule is one pure content transform; the engine derives drift as `apply(current) !== current`, so check and apply cannot disagree by construction.

- Why: the prior-art tools have no check method either (Rector's `refactor()` returns "no change"); a hand-written check can disagree with its apply — the rule reports "satisfied" while apply would still change the file, and CI flaps. Deriving removes that bug class.
- Free generic property test: `apply(apply(x)) === apply(x)` — the engine asserts idempotency over every rule's fixtures with no per-rule test code.
- Dry-run stays structural, not a feature: the pipeline computes the whole plan in memory and `Engine::apply` is the only writer, so `--check` runs every rule's transform without touching disk.
- Every rule carries a mandatory static **description** (what it enforces — feeds the drift report, the docs generation, and the prior-art "description + sample" lesson). A dynamic **`explain(current)`** ("level 5 is below the floor of 6") is opt-in for rules whose drift isn't self-evident from the diff; the computed diff is the fallback.
- Rejected: a mandatory `check()` on the base contract (prior-art lesson 2 read literally) — see above.

### Rule identity = the class name

- Config and disable lists use the FQCN (`PhpStanLevelFloor::class`) — refactor-safe, IDE-autocompleted, and no parallel registry of string IDs to keep in sync (rejected: a string-ID registry is a second source of truth).
- Human-facing output (drift report, generated docs) uses the short class name, plus the target path when one class has several instances: `PhpStanLevelFloor (projects/ai/phpstan.neon)`.
- Trade-offs, accepted with their fixes:
  - Renaming a rule class breaks consumers' disable lists → a rename is a major version bump; optionally keep a deprecated class alias for one release.
  - One class instantiated several times (a key rule for two keys): disabling by class disables all instances → if a real per-instance case appears, add per-instance identity keyed on class + target path. Don't build it now.

### File-level absence: deferred, kept representable

Content-level enforce-absence (remove a key / line / block) is just another transform and needs nothing special. Whole-file removal is out of scope for now: `ChangeKind` has no delete case and the engine only ever writes.

- Candidate mechanism — recorded as a possible path, **to confirm or reject at R0**, not yet a decision: the symmetric null. `apply(?string $content): ?string`, where null in = file absent (creation needs that anyway) and null out = file should not exist. The contract would express deletion from day one while the engine refuses null output until a delete branch (`ChangeKind::Delete`, a delete-capable `Filesystem` port) is actually built.

## Open choices (with recommendations)

1. **Split "what" from "how", or self-contained per-format rules?** The cleaner answer: instead of one generic `FullFileRule` plus a separate apply-strategy, have **per-format rule classes** — `EditorConfigRule`, `GitignoreRule`, … — each self-contained, baking its format knowledge in (sharing a common `AbstractBlockRule` for the marker mechanics). The format-specific class *is* the "how", so no separate strategy layer or `applicable()` pairing is needed, and it grows well — a contributor adds a `FooRule` for a new format. Caveat: for format-*generic* families (an `ImportRule` that varies only by a small syntax detail across `phpstan.neon` / `rector.php`), N per-format classes are overkill — there a single rule with a tiny format-applier is lighter. **Recommendation:** decide per rule *family* — per-format classes for the full-file/block family (this resolves the split cleanly); a single rule + small applier only where a family is genuinely format-generic. Note `.editorconfig` and `.gitignore` currently do identical block-placement, so they can start on one shared base and split when real format-specifics appear (editorconfig key-merge, gitignore line-union).
2. **Ordering.** Rules can share a file. A global integer priority is the known pain point. **Recommendation:** rules own disjoint sections + idempotent apply (no ordering needed), or a declared before/after dependency — avoid a priority integer.
3. **Config shape.** **Recommendation:** a per-rule typed value object now; adopt a self-describing schema only if a rule's config gets rich.
4. **Removal vs disabling vs un-applying a dropped rule** — three distinct things, worth keeping apart:
   - **Disable a rule** = stop enforcing it: do nothing, and leave whatever is already in the file untouched (matches every prior-art tool — non-registration / `severity=0`). No provenance, no undo.
   - **A "remove" rule** = a rule whose `apply()` enforces *absence* (delete a key / line / block if present, no-op if not). This is a first-class, idempotent operation — enforce-absence is just the mirror of enforce-presence — and needs no provenance.
   - **Un-applying a *dropped* rule** = undoing the past effect of a rule no longer configured at all. *This* is the hard one that needs provenance (a state file), because nothing in the config still describes what to undo.
   Note: disabling a parent rule stops it *re-adding* its content but does **not** clean up what it already added — deleting that is a separate "remove" rule (or dropped-rule undo). **Recommendation:** support enforce-presence + enforce-absence rules and external disable now (all cheap); defer dropped-rule undo (state file) until manual cleanup is a burden. Whole-file removal is separate again — deferred but kept representable, see the pre-R0 decisions above.

## Testing rules

Carry the fixture discipline forward from the block engine: each rule ships a **before/after sample**, which doubles as its test fixture (Rector refuses to define a rule without one). Concretely, the existing scenario suite (`input/` → `expected/` fixtures synced and asserted) becomes **per-rule fixtures** — one small before/after case per behaviour the rule has (from-scratch, already-satisfied/idempotent, and value-aware edges like "already higher, leave it"). The rule is the unit; its fixtures are the behaviour catalog and the worked examples for consumers. Any change to a rule's behaviour adds / updates / removes its fixture.

**The rule catalog docs are generated from the fixtures (decided 2026-07-15).** The unit of documentation is the *scenario*, not the rule: the generator renders each rule's description plus **all** its scenarios as before/after pairs — unlike Rector's single code sample, which often can't show a rule's whole point. Required shape: each scenario's kebab-case directory name becomes its example heading; an optional one-line caption file covers cases where the name isn't enough. One source feeds tests and docs, so neither can rot alone. The generator itself is part of the docs phase, but the fixture shape is binding now so nothing needs restructuring later.

## Sequence

- **R0 — design the `Rule` interface on paper**, tested against three probes at once — `FullFileRule` (Tier C), `ImportRule` (Tier A), and a `PhpStanLevelFloor` value-aware rule (Tier B, the only tier that must *read* current values; the floor is its hardest representative, a blind key-set being a degenerate case) — so the contract isn't block-shaped or write-only. Decide the remaining open choices above and confirm/reject the symmetric-null signature.
- **R1 — refactor to `Rule`**, with the current block work as `FullFileRule`. Reuse the pipeline and its tests.
- **R2 — `ImportRule` (Tier A)** — highest value; stress-tests the abstraction on a very different mechanism.
- **R3 — structured / key rules (Tier B)**, one format at a time.
- **R4 (later) — a state file** for clean removal, only if manual cleanup proves a burden.
