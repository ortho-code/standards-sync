# Prior approaches (design history)

Before the current engine, the tool went through three earlier designs. This records them so we don't relitigate settled ground or repeat abandoned ideas. All three shared two traits the current engine deliberately dropped: they **wrote files during the run** (no plan, no dry-run, no drift check), and each run targeted a **single project directory**.

```
Approach A: Symfony console app, one class per tool        (master, tags 0.1..0.2.4)
Approach B: per-tool Task classes + parse/merge layer (WIP, abandoned → moved to old/)
Approach C: singleton Application + RuleSet + Task/CopyFile (feature/start-again)
   └─ pivot: pure plan/apply pipeline, managed blocks (current)
```

The branches, tags, and the `old/` tree named here live in the private predecessor repository; this repository's history starts at the block-model baseline.

## Approach A — Symfony console app, one class per tool

A plain-PHP helper that grew into a full `symfony/console` micro-application (`Kernel` + `MicroKernelTrait`, `bin/bvcs` on `symfony/runtime`). One class per tool in `src/Tool/` (`EditorConfig`, `PhpStan`, `Composer`, …), each implementing `ToolUpdateInterface::update()`; `UpdateService`/`RemoveService` iterated a DI-wired tool list. Canonical files lived in the package's `dist/`. Config was **not consumer-declarable** — the standard was hardcoded into the package; a consumer only chose *when* to run it. Sync was **whole-file copy with overwrite**. Invoked manually and via a Composer hook (`hooks/Composer/PostPackageUpdate.php` → `passthru(... bin/bvcs ...)`).

Why abandoned:
- A whole Symfony framework inside a dev dependency, for what is a file copier — heavy and awkward.
- The standard baked into the package: no split between a reusable engine and an org ruleset; no way for a consumer to pick or extend rules.
- **The `PostPackageInstall` hook never worked — at install time the autoloader isn't refreshed yet, so the package's classes can't load** (removed; documented in its `TODO.md`). See `auto-invocation` in [rule-model.md](rule-model.md): the fix is the command-level events, not the package-level ones.
- Copy-overwrite destroys project-local additions; no dry-run / drift check.

## Approach B — per-tool `Task` classes + a parse-and-merge layer (WIP, never shipped)

A refactor of A aimed at the overwrite problem. Tools were split into granular `Task` classes (`UpdateConfigFile`, `AddCheckScriptToComposer`, `UpdateGitIgnoreFile`, …). A merge layer let config files be **updated in place** rather than clobbered: `Core/Parser/` (`JsonParser`, `YamlParser`, `XmlParser`, `PhpParser` via `nikic/php-parser`'s format-preserving printer) + `Core/Updater/` (`CopyArrayValueFromSource`, `CopyHigherArrayValueFromSource` — e.g. raise PHPStan level only if lower, `DelegatingArrayUpdaterFromSource`). So `PhpStan/Task/UpdateConfigFile` parsed both sides, applied per-key updaters, and dumped the merged result.

Why abandoned: literally a "WIP commit", never finished. The per-key merge ambition multiplied moving parts — a parser plus updaters plus a task per format and per key, with heavy third-party deps and merge logic already getting unwieldy. The author "started all over again" and moved the tree to `old/`.

**Keep in mind — the *idea* was good; the *implementation* was the trap.** B's goal (keep the consumer's own values, merge the standard into specific keys — including smart merges like "raise PHPStan level only if lower") is exactly the value-aware Tier B work we want. What sank it was *how*: a **generic parser + updater framework per format**, built upfront, before shipping anything. The rule model inverts that — each rule does the narrow parse-check-set it needs for its one key, no universal merge engine — so the weight isn't there. Revisit the *goal* freely; do **not** rebuild the generic framework. See "value-aware rules" in [rule-model.md](rule-model.md).

## Approach C — singleton `Application` + `RuleSet` + `Task`/`CopyFile` (immediate predecessor)

A clean-slate rewrite, framework dropped. A `singleton` `Application::getInstance()` holding a flat rules array and one project dir; `RuleSet` composing rule sets into a hierarchy (`addRule`/`addRuleSet`); `RuleInterface` an **empty marker** (rules were pure data); `Rule/CopyFile` (a declaration) vs `Task/CopyFile` (the executor) — a deliberate rule-vs-task split; `DelegatingTask` tried every task per rule and swallowed `UnsupportedRuleException`. The consumer dropped a `coding-standards.php` that **executed against the singleton**: `Application::getInstance()->addRuleSet(new OrgRuleSet())`. `bin` then `require_once`'d that file — **loading the config *was* the side effect** that populated the singleton (no return value). Sync was `Filesystem::copy(..., overwrite: true)` — immediate, unconditional, one file at a time.

Why abandoned (now the engine's standing invariants):
- **Singleton + single root** — can't sync several roots in one process; global mutable state is hard to test.
- **Side-effecting config load** — `require_once`-ing a file to mutate a singleton is opaque and non-re-entrant. The current design makes `coding-standards.php` *return* a `SyncConfig` value.
- **Write-during-run, no plan** — no dry-run, no `--check`. The redesign is a pure plan pipeline with a single writer.
- **Whole-file overwrite** — can't co-manage a file that also holds project-local content. The redesign owns only a labelled marker block; everything outside stays the repo's.

## What not to repeat

- **No singleton, no side-effecting config.** Config is a value returned from `coding-standards.php`; the engine stays re-entrant and multi-root.
- **No write-during-run.** Keep the plan/apply split so `--check` and dry-run stay possible; one writer only.
- **No whole-file overwrite** for files a consumer also edits — own a bounded region.
- **Don't rebuild B's generic parser+updater framework.** Value-aware key merging (the "raise level only if lower" idea) is wanted — but as individual rules doing narrow, targeted parsing (Tier B), not a universal parse-and-merge engine built upfront. The framework was the weight, not the idea.
- **Don't put a framework in the dev dependency (A).** The engine is a transform pipeline; keep it small, wire adapters at a composition root.
