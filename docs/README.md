# Design docs

The living design record and project documentation for this engine — findings, the direction we're taking, the decisions we've made (with their reasoning and the alternatives we rejected), and the durable documentation of architecture, conventions, and authoring. Committed so they travel with the code and survive across working sessions.

## Working conventions — read before changing anything

- **Re-read these docs before working on the engine.** They hold the decisions and *why* they were made. Steering without them risks re-litigating settled calls or quietly contradicting them.
- **Propose and get agreement before changing a recorded decision.** If a decision here no longer fits, say so explicitly — what you'd change and why — and get a yes before editing code or these docs. Never reverse a decision silently.
- **Record new decisions where they live**, dated, with the reasoning and the alternatives rejected — so the next reader inherits the *why*, not just the outcome. (Lightweight ADR style; no separate log to keep in sync.)

## Index

- [rule-model.md](rule-model.md) — the direction: the goal, the tiers of config-sharing, the `Rule` abstraction, prior art (PHP tooling), open choices with recommendations, auto-invocation, value-aware rules, and how rules are tested.
- [architecture.md](architecture.md) — the pure plan pipeline, the deptrac-enforced layers, the extension seams, and the invariants.
- [prior-approaches.md](prior-approaches.md) — the three earlier takes on this tool and what not to repeat.
- [prior-art.md](prior-art.md) — how other ecosystems solved this (copier/cruft, projen, mrm, renovate) and the alternatives they suggest.
- [requirements.md](requirements.md) — determinism, idempotency, atomic writes, the `--check`/CI enforcement model, and the security posture.
- [distribution.md](distribution.md) — naming, publishing, versioning, delivery modes, and how consumers control the pace of change.
- [authoring-org-packages.md](authoring-org-packages.md) — what a consumer org package declares, where its distributed content lives, and how it tests itself.
- [conventions.md](conventions.md) — code and test conventions, the scenario-catalog rule, and the commands.
