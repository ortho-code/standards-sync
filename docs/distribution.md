# Distribution, versioning, and the pace of change

How the packages are published and how consumers control how fast the standard changes. The dated trail behind the names and the release line: [the distribution record](history/distribution.md).

## Naming

- **The engine is `standards-sync`** — the name describes the mechanism (it syncs standards), not the domain. The composer package is **`ortho-code/standards-sync`**, the PHP namespace **`OrthoCode\StandardsSync\`** (tests under `Tests\OrthoCode\StandardsSync\`), and the GitHub home matches the vendor.
- **Org packages are named freely — nothing is enforced or checked.** The recommended convention is the descriptive `<org>/coding-standards`, because that package literally *is* the org's coding standards. The engine is the tool; the org package is the standard. Consumers require the org package and rarely type the engine's name.

## Packages

- **Engine** (`standards-sync`, this repo) — the reusable pipeline + rule types. Published to Packagist; org packages depend on it.
- **Org package(s)** — depend on the engine, return a `SyncConfig`, ship the actual standard. Named freely (see Naming); one per organisation; can compose a hierarchy via `include()`.

## One package, tool-runtime-free rules

Every tool family (phpstan, rector, …) ships in this one engine package, because rules are text transforms: they never execute the tool, import its classes, or reference its constants, so requiring the engine adds zero tool dependencies to a consumer — a project that ignores the Rector rules never needs rector installed. Composer has no conditional requires; the moment a rule family would need the tool's own code (its constants inside our types, or running the tool to validate synced config), that family moves to its own package (`standards-sync-rector`, the phpstan-extension / rector-symfony ecosystem pattern) so only its users carry the dependency. Those are the two recorded triggers for a split — until one fires: single package, and `suggest` entries may hint at the tools a family targets. The org-package layer is the real gate anyway: an org that does not use Rector declares no Rector rules.

## Local dev vs published

For local co-development, a composer **`path` repo with `symlink: true`** (org → engine) works well — gitignoring `composer.lock` in the org package keeps the link live. Either way it is a dev convenience; the release path is a **public Packagist tag** for the engine (and an org package, if public). Whether an org package is public or lives in a private registry / VCS repo is that org's own channel decision.

## Delivery modes

Manual first: `sync` / `sync --check` run by hand and in CI, and that is enough for now. Two automated modes are on the roadmap, deliberately not near-term:

- **PR bot** (renovate-style) — runs sync and opens a PR with the diff. Preferred first when org-wide rollout arrives: reviewable, no local mutation, no `allow-plugins` trust grant (see the security posture in [requirements.md](requirements.md)).
- **Composer plugin** (apply on composer events) — the only propagation bound to the `require` itself, but the widest trust surface; opt-in if ever built.

## Release status

The current line is **v0.x, without a compatibility promise** — SemVer's v0 semantics apply, so v0 minors may break. The BC-surface audit (API vs. internal, per class — the contract-first/never-first split in [conventions.md](conventions.md), and the rendered output too, since recognising what earlier versions wrote is a compatibility surface of its own — see the invariant in [architecture.md](architecture.md)) runs before the first v1 tag, which is where the versioning commitments below begin to bind. The engine is published as public Packagist tags; what each release changed is in the [changelog](../CHANGELOG.md).

## Versioning and the pace of change

The standard evolves (line length 120→140, a new rule, a stricter floor). Consumers receive changes on `composer update`, so they need a way to control the pace:

- **SemVer of the standard package** — a breaking tightening is a major bump; consumers pin a constraint and upgrade deliberately.
- **Per-rule disable** — a consumer turns off a specific rule they can't adopt yet, without abandoning the whole standard (the enable/disable mechanism from the rule model).
- **Import (Tier A) rides `composer update`** — for imported config the version constraint *is* the pace control.

## What a consumer installs

Composer installs the archive `git archive` builds, so `.gitattributes` decides what reaches a consumer's `vendor/`: `bin/`, `src/`, the manifest, the licence, the readme and the changelog.
The export-ignore lines for everything this repository lints, tests and syncs itself with come from the managed block of the standard it consumes; `/docs`, `/deptrac.yaml` and `/docker-compose.yml` are its own lines, below the block.
