# Prior art in other ecosystems

We're not the first to keep config in sync across projects. These tools solved pieces of the problem; two of them answer open questions of ours directly. Worth studying before building the equivalents.

## Template sync with a state file — `copier`, `cruft` (Python)

Both render a project from a template and, crucially, support *updating* an already-generated project when the template changes — via a committed **state/answers file** (e.g. `.copier-answers.yml`) that records what was applied, enabling a 3-way merge on update and clean handling of removed pieces.

**Relevance:** this is exactly the **provenance mechanism our deferred "un-apply a dropped rule" needs** (open choice #4 in [rule-model.md](rule-model.md)). If manual cleanup becomes a burden, a hidden state file is the proven answer — study copier's shape rather than invent one.

## Generate-not-merge — `projen` (AWS)

You define project config in code; projen **fully generates** each config file and marks it "managed — do not edit by hand." There is no merging into user content: the generated file is wholly owned, and local intent lives in the code spec.

**Relevance — a real alternative we're rejecting, and should say why.** projen sidesteps ownership/merge entirely (the file is regenerated, never merged). It's clean but **greenfield-oriented and hostile to existing repos** — you must move all config into the spec and stop hand-editing the files. We deliberately chose **co-manage-a-region** (own a bounded block / specific keys, leave the rest to the repo) so existing repos adopt gradually without surrendering their files. Recorded so we don't drift toward "just regenerate everything" without re-weighing the trade-off.

## Codemods for config — `mrm` (JS)

Task-based "check and apply" over config files, trying not to clobber user edits — the closest sibling to our goal. A reference for idempotent apply and the "don't overwrite what the user changed" discipline.

## Delivery as pull requests — `renovate`, `dependabot`

They keep dependencies fresh by **opening PRs**, not mutating the working tree. A third auto-invocation option beyond the consumer script-hook and the composer plugin (see auto-invocation in [rule-model.md](rule-model.md)): a bot that opens a PR with the sync diff. Reviewable, no local mutation, no `allow-plugins`. A plausible future delivery mode, especially for org-wide rollout across many repos.
