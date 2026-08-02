# standards-sync — the sync engine

PHP/composer engine that keeps config files in sync across repos via **managed blocks**: per-package labelled marker regions (e.g. `# >>> <label> (managed) >>>` … `# <<< <label> <<<`) whose inside is owned and synced, whose outside belongs to the repo.
Org packages declare *which* files to sync and *what* they contain; this engine understands the formats and writes them.
Open-sourced once stable — keep this file project-facing (no personal workflow prefs).

## Where things are documented

`docs/` is the living design record **and** the project documentation — the repo stands alone without this file.

- Direction: [rule-model.md](docs/rule-model.md) — the rule-based model is built (R0 contract, R1 fold); the rule library ships the block mechanism (`ManagedBlock`) plus per-tool PHPStan, Rector and ECS families; org packages author against `Authoring/` (`Package` + `Standard`).
- Architecture + invariants: [architecture.md](docs/architecture.md) — the pipeline, layers, seams; hold the invariants.
- Authoring an org package: [authoring-org-packages.md](docs/authoring-org-packages.md).
- Code + test conventions, commands: [conventions.md](docs/conventions.md).
- Requirements, prior approaches/art, distribution: see the [docs index](docs/README.md).

## Working rules

- **Read `docs/` before working on the engine.** It holds the decisions and *why* they were made. **Propose + get agreement before changing a recorded decision** (never reverse one silently). Record new decisions there: what, why, and what was rejected, dated.
- **References are one-way.** This file may point at docs and code; nothing in the repo (README, `docs/`, code, comments) may reference this file or any Claude config as the home of knowledge. Sole exception: a factual mention that the repo uses and maintains Claude config at certain paths, where that's genuinely worth stating. Check with `git grep -in claude -- ':!CLAUDE.md'` after doc changes.
- **Any change to core pipeline behaviour must add, update, or remove the matching scenario fixture** — the scenario suite is the behaviour catalog (see conventions).
- Validate with `composer app-run-tests` and `composer deptrac` after changes.

## Branching

Work happens on `main`. This repository's history starts at the rule-model design point; the block-model baseline and the earlier takes are preserved in the private predecessor repository.
