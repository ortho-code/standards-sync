# Roadmap

What is ahead, with each item's trigger. The completed trail — everything already built, with dates and reasoning — is [the decision record's sequence](history/rule-model.md#sequence).

## Before the first v1 tag

- **The BC-surface audit**: per class, mark what is API and what is internal, before any compatibility promise is made. Until then the release line is v0.x without a promise — see [release status](distribution.md#release-status) and the contract-first/never-first split in [conventions.md](conventions.md).

## Candidate tool families

The next family is picked from this list rather than from memory; nothing here is designed, and every config surface is unverified — a design phase opens by reading the tool's own source.

- **`Dockerfile`** — needs no engine work: it is comment-bearing, so `ManagedBlock` handles it today and the whole family is org authoring. Recorded because "already possible" is easy to miss.
- **`compose.yaml`** — waits on nothing technically (the YAML writer exists), but `compose.override.yaml` is the record's one named example of genuine multi-file merging, parked as out of scope — picking this family forces that decision open. Not a casual pick.
- **Release workflow + changelog** — needs no engine work, like `Dockerfile`: a tag-driven release workflow synced as a managed block, a `CHANGELOG.md` seeded one-shot, and release notes extracted from the tag's changelog section — this repository's own release mechanism is the worked example.

## Deferred, with recorded triggers

Each of these is designed as far as its entry records and waits for its trigger; the entries hold the reasoning.

- **A state file for clean removal** (un-applying a dropped rule) — trigger: manual cleanup proves a burden. Marker blocks carry their own provenance, so this only serves unmarked edits; `symfony.lock` is the shape to copy.
- **A delete branch** (`ChangeKind::Delete`, a delete-capable filesystem port) — whole-file removal is refused until a real need arrives; content-level enforce-absence needs nothing special today.
- **Per-instance rule identity** (class + target path) — trigger: a real case where disabling by class is too coarse.
- **Union returns for same-label blocks** (a `GitignoreRule` opting in) — the default stays last-wins; union arrives as chosen construction behaviour with a pre-fold coalesce step.
- **A generic `ImportRule` convenience** over the per-tool classes — trigger: the tail of tools grows long; never first.
- **The per-root rule-factory seam** — trigger: the first real consumer with sync roots below the project top (a central-vendor monorepo).
- **`ManagedBlock` per-candidate syntax** (adopting `AppliesAtPath`) and an abstain-when-absent construction flag — trigger: a real same-format case needing either.
- **Enclosed-comment marker support** (`<!-- -->`) — expected trigger: managed README sections; that adds a comment tail to the marker grammar.
- **Catalog generation for org packages** — `RuleCatalog`'s project shape becomes a parameter so an org can generate docs for its own rules; the same work makes the catalog's refusal guards testable. Trigger: an org package wanting generated docs, or the next change to the guards.
- **The phpunit dev pin lifts back to `^13`** when psalm's `sebastian/diff` cap moves.
