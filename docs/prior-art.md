# Prior art in other ecosystems

We're not the first to keep config in sync across projects. These tools solved pieces of the problem; several answer open questions of ours directly. Worth studying before building the equivalents.

## Template sync with a state file — `copier`, `cruft` (Python)

Both render a project from a template and, crucially, support *updating* an already-generated project when the template changes — via a committed **state/answers file** (e.g. `.copier-answers.yml`) that records what was applied, enabling a 3-way merge on update and clean handling of removed pieces.

**Relevance:** this is exactly the **provenance mechanism our deferred "un-apply a dropped rule" needs** (open choice #4 in [rule-model.md](rule-model.md)). If manual cleanup becomes a burden, a hidden state file is the proven answer — study copier's shape rather than invent one. *(Superseded as the reference shape by `symfony.lock` below, which is keyed per contributing package like our rule sets; copier remains the one-template-per-project variant.)*

## Recipe-driven config push with markers and a lock — Symfony Recipes / Flex (PHP)

The closest sibling in our own ecosystem, studied from the Flex source (2026-07-23). Flex is a composer plugin: on `require` it applies the package's *recipe*, a manifest of **configurators** that push config into the consumer repo. The marker configurators (`env`, `gitignore`, `dockerfile`, `makefile`, …) are architecturally `ManagedBlock`, independently converged: labelled blocks (`###> vendor/package ###` … `###< vendor/package ###`), replaced in place when marked, appended when not, removed by regex on uninstall — proven at ecosystem scale. A committed **`symfony.lock`** records, per package, the applied recipe's content ref and the list of files it touched. Updates (`recipes:update`) re-derive what the old and the new recipe each produce, git-diff the two states in a throwaway repository, and `git apply -3` the result onto the project — real three-way merging, with conflicts left as git conflict markers.

**Relevance — four things:**

- **The lock file is the shape for our deferred state file** (open choice #4 in [rule-model.md](rule-model.md), R4), closer to us than copier's answers file: keyed *per contributing package* (matching rule-set composition), storing a content ref plus the touched-files list. It also solves a case our R4 sketch missed: **cross-package reference counting** — before deleting a file on uninstall, Flex checks whether another package's lock entry still lists it. That is exactly our two-layers-co-manage-one-file case: un-apply must not delete what the base layer still owns.
- **Markers are provenance — state is only needed for unmarked edits.** Flex un-applies marker blocks with no state at all; the label in the file suffices. That splits R4: removing everything with a given label (Tier C) never needs a state file; only the unmarked targeted edits (Tier A imports, Tier B values) do. R4 shrinks to "a state file for *unmarked* removal" and can ship in two independent pieces.
- **The patch machinery is the rejection to learn from.** Flex needs git, temporary repositories and blob injection — and still ends in conflict markers — because recipes are one-shot scaffolding: the system cannot recompute the desired end state, only diff two recipe versions. Our rules *are* the desired-state function: update = re-run sync, and value-aware semantics (floor/pin) resolve by intent instead of textual merge. The projen argument from the opposite direction — recorded so nobody proposes version-to-version patching here.
- **Post-create guidance is worth copying.** Flex's `post-install-output` prints recipe-supplied next steps after applying. Our created configs deliberately omit the project-owned parameters (`paths:` / `withPaths()`) — a per-rule "after creating, say this" message in the sync output would tell the maintainer what is deliberately theirs to add. Cheap, and closes a real gap.

Confirmations, no change needed: the composer-plugin delivery mode and its trust cost are already recorded in [distribution.md](distribution.md); Flex's per-version recipe directories exist because recipes live in a central repository — our config rides the org package's own composer versioning, which is simpler; the `add-lines` configurator's `after_target` positioning is a remembered shape for string-anchored insertion, not something to build now.

## Generate-not-merge — `projen` (AWS)

You define project config in code; projen **fully generates** each config file and marks it "managed — do not edit by hand." There is no merging into user content: the generated file is wholly owned, and local intent lives in the code spec.

**Relevance — a real alternative we're rejecting, and should say why.** projen sidesteps ownership/merge entirely (the file is regenerated, never merged). It's clean but **greenfield-oriented and hostile to existing repos** — you must move all config into the spec and stop hand-editing the files. We deliberately chose **co-manage-a-region** (own a bounded block / specific keys, leave the rest to the repo) so existing repos adopt gradually without surrendering their files. Recorded so we don't drift toward "just regenerate everything" without re-weighing the trade-off.

## Codemods for config — `mrm` (JS)

Task-based "check and apply" over config files, trying not to clobber user edits — the closest sibling to our goal. A reference for idempotent apply and the "don't overwrite what the user changed" discipline.

## Delivery as pull requests — `renovate`, `dependabot`

They keep dependencies fresh by **opening PRs**, not mutating the working tree. A third auto-invocation option beyond the consumer script-hook and the composer plugin (see auto-invocation in [rule-model.md](rule-model.md)): a bot that opens a PR with the sync diff. Reviewable, no local mutation, no `allow-plugins`. A plausible future delivery mode, especially for org-wide rollout across many repos.
