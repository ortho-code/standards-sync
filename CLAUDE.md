# standards-sync — the sync engine

PHP/composer engine that keeps config files in sync across repos via **managed blocks**: per-package labelled marker regions (e.g. `# >>> <label> - managed >>>` … `# <<< <label> <<<`) whose inside is owned and synced, whose outside belongs to the repo.
Org packages declare *which* files to sync and *what* they contain; this engine understands the formats and writes them.
Public and published; keep this file project-facing (no personal workflow prefs).

## Where things are documented

`docs/` is the living design record **and** the project documentation — the repo stands alone without this file.

- Design: [design.md](docs/design.md) — the current design core (the `Rule` contract, the tiers, resolution, composition, the `Authoring/` layer); what is ahead in [roadmap.md](docs/roadmap.md). The dated decision trail — per topic and per tool family — lives in [docs/history/](docs/history/README.md).
- Architecture + invariants: [architecture.md](docs/architecture.md) — the pipeline, layers, seams; hold the invariants.
- Authoring an org package: [authoring-org-packages.md](docs/authoring-org-packages.md).
- Rule catalog (generated, never hand-edited): [docs/rules/](docs/rules/README.md) — regenerate with `composer app-generate-rule-catalog` after any scenario fixture or provider change; a freshness test fails while it is stale.
- Code + test conventions, commands: [conventions.md](docs/conventions.md).
- Requirements, prior approaches/art, distribution: see the [docs index](docs/README.md).

## Working rules

- **Read `docs/` before working on the engine.** It holds the decisions and *why* they were made. **Propose + get agreement before changing a recorded decision** (never reverse one silently). Record new decisions there: what, why, and what was rejected, dated.
- **References are one-way.** This file may point at docs and code; nothing in the repo (README, `docs/`, code, comments) may reference this file or any Claude config as the home of knowledge. Sole exception: a factual mention that the repo uses and maintains Claude config at certain paths, where that's genuinely worth stating. Check with `git grep -in claude -- ':!CLAUDE.md' ':!.gitattributes'` after doc changes (the `.gitattributes` export-ignore line is the recorded factual exception).
- **Examples stay generic.** Docs, fixtures and comments use placeholder names (`acme`) and speak of "an org" or "a consumer" — never a real organisation.
- **Any change to core pipeline behaviour must add, update, or remove the matching scenario fixture** — the scenario suite is the behaviour catalog (see conventions).
- Validate with `composer app-run-tests` and `composer deptrac` after changes.

## Branching

Work happens on `main`. This repository's history starts at the rule-model design point; the block-model baseline and the earlier takes are preserved in the private predecessor repository.
