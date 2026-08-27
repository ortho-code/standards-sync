# Distribution, versioning, and the pace of change

How the packages are published and how consumers control how fast the standard changes. Partly decided (naming, delivery roadmap); the release channel details are settled before a v1 release.

## Naming (decided 2026-07-15)

- **The engine is named `standards-sync`.** The name describes the mechanism (it syncs standards), not the domain. "Coding standards" as an engine name was rejected: on Packagist that phrase is colonized by phpcs/ecs style rulesets (`doctrine/coding-standard`, `slevomat/coding-standard`, …), so it miscategorizes the tool — and the domain name rightfully belongs to the org packages.
- **Org packages are named freely — nothing is enforced or checked.** The convention we recommend is the descriptive `<org>/coding-standards`, because that package literally *is* the org's coding standards. The engine is the tool; the org package is the standard. Consumers require the org package and rarely type the engine's name.
- **The engine gets a fresh public repository** with no history carried over: the old history is saturated with the previous owner's identity (names, namespaces, internal pipeline configs) beyond what a rename can scrub, and `docs/` + [prior-approaches.md](prior-approaches.md) exist precisely so the design record survives without the commits. The old repos stay private as reference. Initial GitHub home: `alleknalle/standards-sync`; repo transfers redirect permanently, so the home can move later without breakage.
- **Decided 2026-08-25 — the composer vendor: `standards-sync/standards-sync`.** Both candidate vendors were verified free on Packagist before choosing. The project-named vendor follows the tool's peers (`rector/rector`, `phpstan/phpstan`, `deptrac/deptrac`), stays decoupled from any personal brand, and matches the bin name and the consumer config filename (`standards-sync.php`), which both predate it. Rejected: `alleknalle/standards-sync` — zero rename cost (everything was wired to it), but it frames an org-adoption tool as a personal project, and Packagist's no-redirect rule would have made that permanent. The PHP namespace follows: `StandardsSync\` (tests under `Tests\StandardsSync\`). The GitHub home follows too: a `standards-sync` org (name verified free) receives the repository by transfer, the old URL redirecting permanently.
- **Revised the same date — the vendor follows the maintainer's umbrella organisation: `ortho-code/standards-sync`.** OrthoCode was adopted as the organisation for all the maintainer's projects, so the vendor and the GitHub home move under it (`ortho-code` verified free on Packagist and GitHub; the bare `orthocode` GitHub name is taken, and unrelated products share the word — an orthopedic-coding app, a keyboard kit — accepted as far outside this domain). The namespace takes the full vendor prefix — `OrthoCode\StandardsSync\`, tests under `Tests\OrthoCode\StandardsSync\` — trading the single-segment root for one identity across the organisation's packages (the `symfony/console` shape rather than `phpunit/phpunit`). The tool keeps its own name in the bin and the consumer config filename. The `standards-sync` vendor claim above was never published and the GitHub org is renamed in place, so nothing external breaks.

## Packages

- **Engine** (`standards-sync`, this repo) — the reusable pipeline + rule types. Published to Packagist; org packages depend on it.
- **Org package(s)** — depend on the engine, return a `SyncConfig`, ship the actual standard. Named freely (see Naming); one per organisation; can compose a hierarchy via `include()`. The current test/example org package plays this role until a real one exists.

## One package, tool-runtime-free rules (decided 2026-07-23)

Every tool family (phpstan, rector, …) ships in this one engine package, because rules are text transforms: they never execute the tool, import its classes, or reference its constants, so requiring the engine adds zero tool dependencies to a consumer — a project that ignores the Rector rules never needs rector installed. Composer has no conditional requires; the moment a rule family would need the tool's own code (its constants inside our types, or running the tool to validate synced config), that family moves to its own package (`standards-sync-rector`, the phpstan-extension / rector-symfony ecosystem pattern) so only its users carry the dependency. Those are the two recorded triggers for a split — until one fires: single package, and `suggest` entries may hint at the tools a family targets. The org-package layer is the real gate anyway: an org that does not use Rector declares no Rector rules.

## Local dev vs published

Local co-development uses a composer **`path` repo with `symlink: true`** (org → engine), and `composer.lock` is gitignored in both. That is a dev convenience; the release path is a **public Packagist tag** for the engine (and the org package, if public). The private-packagist mirror was dropped for local dev (it 401'd without a token); the real channel is still to be decided — including whether the org package is public or lives in a private registry / VCS repo, and how a private engine is distributed.

## Delivery modes (roadmap, decided 2026-07-15)

Manual first: `sync` / `sync --check` run by hand and in CI, and that is enough for the current scale. Two automated modes are on the roadmap, deliberately not near-term:

- **PR bot** (renovate-style) — runs sync and opens a PR with the diff. Preferred first when org-wide rollout arrives: reviewable, no local mutation, no `allow-plugins` trust grant (see the security posture in [requirements.md](requirements.md)).
- **Composer plugin** (apply on composer events) — the only propagation bound to the `require` itself, but the widest trust surface; opt-in if ever built.

## Release status (decided 2026-08-25)

The first tag is **v0.x, without a compatibility promise** — SemVer's v0 semantics apply, so v0 minors may break. The BC-surface audit (API vs. internal, per class — the contract-first/never-first split in [conventions.md](conventions.md)) runs before the first v1 tag, which is where the versioning commitments below begin to bind. The engine's channel is a public Packagist tag; publishing follows once the building blocks — docs, CI, composer metadata — are verified.

## Versioning and the pace of change

The standard evolves (line length 120→140, a new rule, a stricter floor). Consumers receive changes on `composer update`, so they need a way to control the pace:

- **SemVer of the standard package** — a breaking tightening is a major bump; consumers pin a constraint and upgrade deliberately.
- **Per-rule disable** — a consumer turns off a specific rule they can't adopt yet, without abandoning the whole standard (the enable/disable mechanism from the rule model).
- **Import (Tier A) rides `composer update`** — for imported config the version constraint *is* the pace control.
