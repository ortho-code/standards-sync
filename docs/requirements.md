# Requirements the engine must hold

Design-level requirements that outlive any particular rule — the *why* behind the invariants in [architecture.md](architecture.md).

## Determinism and idempotency

- **Deterministic output.** Given the same input file + rules, `apply()` produces byte-identical output every run — stable key/line ordering, no time or randomness. Otherwise `--check` reports spurious drift and CI flaps.
- **Idempotent.** Applying twice equals applying once; `check()` after `apply()` reports "in sync." Enforce-presence rules no-op when already satisfied; enforce-absence rules no-op when already absent.

## Atomic per-file writes

A file's rules are folded over its content and the file is written **once, all-or-nothing**. If any rule on a file fails (e.g. an unparseable target), that file is left **untouched** — never half-written. The plan/apply split already supports this: compute the whole desired file, then write. A failure aborts that file's change and is reported, without affecting the other files in the plan.

## Preserve the file's existing style

When mutating in place, preserve the target's line endings (LF/CRLF), indentation, and trailing-newline convention rather than imposing ours. Drift detection compares meaning, not incidental whitespace churn.

## Targeted edits, not parse-dump round-trips (Tier B)

A value-aware rule edits exactly its one key with a narrow, format-preserving edit — textual or token-level where a format library can't round-trip faithfully. Never parse the whole file and dump it back: a round-trip reformats content the rule doesn't own, violating the style-preservation requirement above and drowning the real change in churn. This is also the concrete guardrail against rebuilding the universal parse-and-merge framework that sank the earlier attempt (see [prior-approaches.md](history/prior-approaches.md)).

## Enforcement model — `--check` in CI is the backstop

Delivery (sync-on-composer-event / plugin / PR) makes updates *land*; **`--check`** makes them *stick* — it reports drift and exits non-zero without writing, so CI fails when a repo has diverged from the standard. Delivery and `--check` are complementary: one distributes, the other enforces.

## Security / trust posture

This tool **writes files** and can **run on `composer` events** — a supply-chain surface. A compromised or careless standards package could write arbitrary content into every consumer on each update. The mitigations, and why they shape the defaults:

- Ship as **`require-dev`** — absent from production `--no-dev` installs, so the blast radius is dev/CI only.
- Default to **`--check` (report, don't apply) in CI** with **explicit local apply**, rather than silent apply-on-update. Auto-apply (a plugin on `post-update-cmd`) is more convenient but widens the surface — make it opt-in, not the default.
- The `allow-plugins` gate on a composer plugin is a feature here: it forces the consumer to consent to code running on their lifecycle.
