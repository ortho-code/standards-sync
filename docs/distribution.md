# Distribution, versioning, and the pace of change

How the packages are published and how consumers control how fast the standard changes. Partly decided (naming, delivery roadmap); the release channel details are settled before a v1 release.

## Naming (decided 2026-07-15)

- **The engine is named `standards-sync`.** The name describes the mechanism (it syncs standards), not the domain. "Coding standards" as an engine name was rejected: on Packagist that phrase is colonized by phpcs/ecs style rulesets (`doctrine/coding-standard`, `slevomat/coding-standard`, …), so it miscategorizes the tool — and the domain name rightfully belongs to the org packages.
- **Org packages are named freely — nothing is enforced or checked.** The convention we recommend is the descriptive `<org>/coding-standards`, because that package literally *is* the org's coding standards. The engine is the tool; the org package is the standard. Consumers require the org package and rarely type the engine's name.
- **The engine gets a fresh public repository** with no history carried over: the old history is saturated with the previous owner's identity (names, namespaces, internal pipeline configs) beyond what a rename can scrub, and `docs/` + [prior-approaches.md](prior-approaches.md) exist precisely so the design record survives without the commits. The old repos stay private as reference. Initial GitHub home: `alleknalle/standards-sync`; repo transfers redirect permanently, so the home can move later without breakage.
- **Open — the composer vendor.** Packagist names do not redirect, so unlike the GitHub home this must be right first time. Candidates: `standards-sync/standards-sync` (vendor free, matches a possible future `standards-sync` GitHub org, decoupled from any personal brand) vs `alleknalle/standards-sync`. The PHP namespace and the consumer config filename follow this decision.

## Packages

- **Engine** (`standards-sync`, this repo) — the reusable pipeline + rule types. Published to Packagist; org packages depend on it.
- **Org package(s)** — depend on the engine, return a `SyncConfig`, ship the actual standard. Named freely (see Naming); one per organisation; can compose a hierarchy via `include()`. The current test/example org package plays this role until a real one exists.

## Local dev vs published

Local co-development uses a composer **`path` repo with `symlink: true`** (org → engine), and `composer.lock` is gitignored in both. That is a dev convenience; the release path is a **public Packagist tag** for the engine (and the org package, if public). The private-packagist mirror was dropped for local dev (it 401'd without a token); the real channel is still to be decided — including whether the org package is public or lives in a private registry / VCS repo, and how a private engine is distributed.

## Delivery modes (roadmap, decided 2026-07-15)

Manual first: `sync` / `sync --check` run by hand and in CI, and that is enough for the current scale. Two automated modes are on the roadmap, deliberately not near-term:

- **PR bot** (renovate-style) — runs sync and opens a PR with the diff. Preferred first when org-wide rollout arrives: reviewable, no local mutation, no `allow-plugins` trust grant (see the security posture in [requirements.md](requirements.md)).
- **Composer plugin** (apply on composer events) — the only propagation bound to the `require` itself, but the widest trust surface; opt-in if ever built.

## Versioning and the pace of change

The standard evolves (line length 120→140, a new rule, a stricter floor). Consumers receive changes on `composer update`, so they need a way to control the pace:

- **SemVer of the standard package** — a breaking tightening is a major bump; consumers pin a constraint and upgrade deliberately.
- **Per-rule disable** — a consumer turns off a specific rule they can't adopt yet, without abandoning the whole standard (the enable/disable mechanism from the rule model).
- **Import (Tier A) rides `composer update`** — for imported config the version constraint *is* the pace control.
