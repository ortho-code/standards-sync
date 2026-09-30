# Design docs

The project documentation and its design record, in two genres: the pages in this directory describe the **current state** — how the engine is designed, structured, and used today — and [history/](history/README.md) holds the **dated decision trail** that produced it, with the reasoning and the alternatives rejected. Committed so they travel with the code.

## Working conventions — read before changing anything

- **Re-read these docs before working on the engine.** They hold the decisions and *why* they were made. Working without them risks re-litigating settled calls or quietly contradicting them.
- **Propose and get agreement before changing a recorded decision.** If a decision no longer fits, say so explicitly — what you'd change and why — and reach agreement with the maintainers (an issue or PR discussion) before editing code or these docs. Never reverse a decision silently.
- **A change lands in both genres, in the same commit**: a dated entry in [history/](history/README.md) (reasoning and rejected alternatives included — lightweight ADR style), plus the update to whichever current page it affects. Current pages stay dateless and narrative-free; history entries are append-only — a later entry supersedes, never rewrites.

## Current state

- [design.md](design.md) — the design core: the goal, the tiers of sharing, the `Rule` contract, resolution, composition, the authoring layer, formats and validation.
- [architecture.md](architecture.md) — the pure plan pipeline, the deptrac-enforced layers, the extension seams, and the invariants.
- [authoring-org-packages.md](authoring-org-packages.md) — what a consumer org package declares, where its distributed content lives, and how it tests itself.
- [conventions.md](conventions.md) — code and test conventions, the scenario-catalog rule, and the commands.
- [requirements.md](requirements.md) — determinism, idempotency, atomic writes, the `--check`/CI enforcement model, and the security posture.
- [distribution.md](distribution.md) — naming, publishing, versioning, delivery modes, and how consumers control the pace of change.
- [roadmap.md](roadmap.md) — what is ahead: the v1 gate, candidate tool families, and the deferred items with their triggers.
- [rules/](rules/README.md) — the generated rule catalog: a page per shipped rule with its scenarios as before/after examples, plus family pages for cross-rule compositions; regenerated from the rule library and scenario suite (`composer app-generate-rule-catalog`), never edited by hand.

## History

- [history/rule-model.md](history/rule-model.md) — the rule-based model's trail: the original direction, the contract decisions (R0–R2), the cross-family decisions, and the build sequence.
- The tool families' decision records: [phpstan](history/phpstan-family.md), [rector](history/rector-family.md), [ecs](history/ecs-family.md), [psalm](history/psalm-family.md), [composer](history/composer-family.md), [deptrac](history/deptrac-family.md), [renovate](history/renovate-family.md), [phpunit](history/phpunit-family.md), [github workflow](history/github-workflow-family.md).
- [history/distribution.md](history/distribution.md) — the naming and release decisions.
- [history/prior-approaches.md](history/prior-approaches.md) — the three earlier takes on this tool and what not to repeat.
- [history/prior-art.md](history/prior-art.md) — how other ecosystems solved this (copier/cruft, projen, mrm, renovate) and the alternatives they suggest.
