# The GitHub workflow family — decision record

Part of the [rule-model design record](rule-model.md), split out per family; the contract, the doctrine, the cross-family decisions, and the roadmap live there. Entries are chronological under their original headings.

## Agreed 2026-09-30 — a workflow is held to containment, not to a managed block

**Why.** A standard that ships a CI workflow as a managed block owns the whole file, because a single-document YAML block cannot be followed by more of the document (the [roadmap's block extension-point entry](../roadmap.md)).
So a repository can add nothing to a declared job — no step, no input, no service, no job of its own beside it — and it cannot take an action or runner update ahead of the standard: a bot updating the synced copy fails `sync --check` until a release of the standard carries the same update, which ties every consumer's CI to that release cycle.
Both are one mismatch: a block is a copy, and the engine's positioning is floors, not copies ([design.md](../design.md)).

**What.** A rule that enforces that a consumer's workflow **contains** the declared one: everything the template declares is present, and the project may add more; action refs and runner labels are minimum versions; and sync edits the file's own text in place, never re-printing it.

*Rejected*:

- Minimum versions inside `ManagedBlock`, a `floors:` kind passed to the generic rule — GitHub knowledge on a rule that also places `.gitignore` blocks, and additions stay impossible.
- Insertion points inside the block — only the places a standard foresaw, with no way to add an input or a service, and versions still exact.
- A reusable workflow or composite action the standard ships and consumers call — GitHub composes only at job or step boundaries, and the called ref is a second version channel beside the composer requirement.
- Keeping the block and treating the failing bot pull request as the signal that a release is due, or having the bot skip the managed files — both leave the consumer on the standard's release cycle.

**Verified facts** (read 2026-09-30 from GitHub's workflow syntax reference and GitHub's own parsers — `actions/languageservices`' `workflow-parser` and the C# parser in `actions/runner` — and from Renovate 44.125.1's source):

- **Order.** Steps run in order ("Steps are executed in order and are dependent on each other"), except the `background` and `parallel` steps added in 2026-06; jobs run in parallel, so their order means nothing; `runs-on` labels are AND-ed and held as a set; `on` events and `types` are OR-ed. Branch, tag and path filters are ordered once `!` is used: "The order that you define patterns matters. A matching negative pattern (prefixed with `!`) after a positive match will exclude the Git ref."
- **Identity.** Job ids are unique within `jobs`, and every mapping rejects keys that differ only in case. A step `id` is unique within its `steps` list; a step `name` is optional and not unique, and one action may run in several steps — so a step has no stable identity unless it declares an `id`.
- **Equivalent forms**, from the parser's converters: `on: push` = `on: [push]` = `on: {push: null}` = `on: {push: {}}`; `needs: a` = `needs: [a]`; a filter takes a single string or a list; `runs-on: x` = `runs-on: [x]`; an absent `if` is `success()`.
- **YAML.** Anchors and aliases are supported since 2025-09-18; merge keys are not; a file holds one document; `on` reads as the string `on`, the YAML 1.2 core schema having no `on` boolean.
- **Where more is less.** "A job that is skipped will report its status as "Success". It will not prevent a pull request from merging, even if it is a required check.", and "If a job fails or is skipped, all jobs that need it are skipped" — whereas a workflow skipped by branch or path filtering leaves its required checks pending, blocking the merge. Adding `tags` to a `push` that only filtered `branches` stops branch pushes from triggering it at all, and adding `types` to `pull_request` replaces its three default types.
- **Renovate's edits.** An action update rewrites `owner/repo[/path]@<new>`, or `@<sha> # <new>` when digests are pinned; a runner update rewrites `<name>-<new>`. Its `github-runners` datasource knows `ubuntu`, `macos` and `windows`, never updates `-latest`, and orders with Docker versioning: a suffix after the version (`-arm`, `-intel`) is a compatibility class that must match, so `24.04-arm` only ever moves to `26.04-arm`.

**Measured.** Against 29 workflow files from 8 widely used PHP libraries: 82% of steps carry a `name` and 3% an `id`; the additions projects make to a checks job are overwhelmingly extra `setup-php` inputs (`extensions` in half of them, `tools`, `ini-values`); a PHP matrix replaces the declared `php-version` with an expression in 13 of 28 `setup-php` steps. A throwaway containment prototype over twelve variations of a standard's workflow confirmed the common additions stay in sync and found the five cases the decisions below settle: weakening additions, step identity, digest-pin comments, expressions in declared values, and equivalent forms.

**Decisions.**

1. **A declared step is identified by its `id`**, which a standard's template must give every step. A project's step without that `id` is adopted when it contains the declared step, the first in order when two do, and sync adds the `id` to it — so a workflow written before ids, whether a synced block or a project's own, gains ids rather than duplicates. *Rejected*: matching a `uses` step by its action, which binds a declared checkout to a project's second checkout of another repository; matching a `run` step by its text, which turns a project's edit of a declared script into a missing step plus one of the project's own, and duplicates it on sync.
2. **Additions are the project's, except those that can make a declared job or step not run, or not fail, without failing loudly**: `if` and `continue-on-error` on a declared job or step, `needs` added to a declared job, and labels added to a declared `runs-on` are drift, and sync removes them. *Rejected*: every addition allowed, which lets `continue-on-error: true` make the checks unable to fail; a closed list of allowed additions, which fails closed on every key GitHub adds and ties consumers to engine releases again.
3. **A declared trigger's filter lists may gain entries while neither side holds a `!` pattern, and are exact once either does; a filter key added to a declared event is drift.** Pattern order matters only once `!` is used, and an added filter key narrows the trigger or silences it.
4. **Declared scalars are exact, expressions included**: `php-version: ${{ matrix.php }}` in place of a declared literal is drift, and a matrix belongs in a job of the project's own. *Rejected*: letting an expression satisfy any declared scalar, which lets `${{ '7.4' }}` through.
5. **GitHub's equivalent forms are normalised; a shape sync cannot edit into the declared form is refused**, naming the node and what to write. *Rejected*: restructuring the node, which rewrites the project's formatting for a case two of the sampled files show.
6. **Minimum versions.** A tag compares on the components both sides spell, so `v7` satisfies `v7.2.0` as a moving major tag does; a digest pin compares by the version in its comment and is kept verbatim; the action, path included, must match exactly; local, same-repository and `docker://` references are exact. A ref naming no orderable version — a branch, a bare SHA — is drift, and sync writes the declared one. Runner labels compare within one name and suffix; `-latest` and unversioned labels are exact.
7. **A template's comments are written when the file is created and when a missing element is inserted**, travelling with that element, and are never enforced afterwards.
8. **The rule can take over a managed block**: given the block's label, it removes that label's two marker lines on the first sync and keeps the content. A migration aid only.
9. **A declared step the project moved before another declared step is drift, and sync refuses**, naming the step to move: a moved step changes what the project's own steps between them see. *Rejected*: moving the step's text, which edits across the project's own steps.

The reader behind all of this is the entry below.

## Agreed 2026-09-30 — the reader: a hand-written locator that decodes each value alone

**The backend re-assessment** the [conventions](../conventions.md) ask for at every new `Formats/` member, probed 2026-09-30 against 369 real-world workflow files and 18 hand-made edge cases (CRLF, a byte-order mark, no final line break, `|+`, anchors, `-   key` indentation), with the bar that every byte outside an edit stays as it was:

- **symfony/yaml 8.1.8** reads values and cannot locate them: plain arrays, no positions, comments dropped, and a comment-preserving mode declined upstream (symfony/symfony#22516).
- **symfony/maker-bundle's `YamlSourceManipulator` 1.68.0** failed: every edit on a standard workflow threw, a no-op included, because a list whose items repeat their first key misreads the next item; with an unreleased fix it walks 284 of the 369, and inserting a step still produces invalid YAML.
- **pecl yaml 2.3.0** exposes no positions and no comments.
- **horde/yaml 3.0.1** has the right API — path-addressed nodes with line and column, `setValue`, `insertItemAt` — but re-prints the whole document, and a no-edit round trip is byte-identical for only 183 of the 369.
- **aeliot/yaml-token 0.1.0** is a lossless token tree, 368 of 369 byte-identical and all 18 edge cases, with line and column; it locates and cannot build, so edits are text splices on top of it. One maintainer and 254 downloads at the time: **the candidate for the next re-assessment.**
- **mougrim/yaml-cst 0.0.0** locates with byte spans through tree-sitter, over `ext-ffi` and two native libraries composer cannot install in a consumer's CI.

Renovate and Dependabot both edit workflows as text at located positions and never print YAML from a tree.
So the edits are hand-written text whichever backend locates, and a hand-written locator was measured before choosing it.

**The spike.** A throwaway locator and editor read 388 of the 388 files it accepted exactly as symfony/yaml does, refusing one by design (anchors), and made 15,092 edits across ten kinds — raising a ref, correcting a value in its own quoting, adding inputs, keys and `with` blocks, inserting and removing steps and keys, adding filter patterns to block and one-line flow lists — each output parsing to exactly the expected data, touching no line outside the edit, and reading the same when located again.

**Why each value decodes alone.** The spike's only failures were a symfony/yaml misread, reproduced on its 6.4, 7.4, 8.0 and 8.2-dev branches and on 8.1.8: when a document opens with two or more lines holding only a comment or nothing, a block scalar nested in a mapping or sequence, with more content after it, loses its final line break.

```yaml
# one
# two
a:
  - b: |
      x
c: 1
```

symfony/yaml reads `b` as `"x"`, where clip chomping keeps the final line break and PyYAML returns `"x\n"`; `|+` and `>` lose it the same way.
The cause is in the parser's bookkeeping: `cleanup()` strips the leading lines and counts them into the line offset, the root's line total leaves them out, and the last-line check compares the two, turning true as many lines early as were stripped.
In a containment check such a misread is drift sync can never fix — sync writes the declared text, the text is already there, and the check fails again — and a workflow opening with a comment header is common.
Decoding each value from its own source text avoids it, because the fragment carries none of the context that triggers it.

**What.** `Formats/Yaml/Tree/` locates a document's block structure by line and column without re-printing it, and refuses what workflows do not need rather than guessing: anchors, aliases and tags, merge keys, complex keys, duplicate keys, several documents or a directive, tab indentation, and a sequence opening on a key's or a dash's line.
symfony/yaml becomes a runtime dependency for one class, `YamlScalarDecoder`, which decodes a single value's text; a deptrac layer of its own, `YamlDecoding`, keeps every other `Formats` class vendor-free, the way `ComposerRules` scopes composer's semver library.
For a consumer the engine is always a development dependency, so what it requires never reaches production; what remains is that composer resolves both sections as one graph, so a requirement still has to agree with the consumer's own packages.
*Rejected*:

- symfony/yaml as the whole-document reader, for the misread above.
- The locator decoding scalars itself as well, which removes the vendor edge at the cost of reimplementing YAML's scalar grammar for the same result.
- A grant for the YAML format as a whole, `Formats/Yaml/` as the layer; tried in review the same day and reverted.
- Opening `Formats → Vendor` for every format, deferred rather than refused: this repository's `require-dev` holds the other formats' parsers (nette/neon, colinodell/json5), which a consumer never installs, so a writer using one would pass every check here and fail in every consumer. Opening it needs a check that `src/` uses only packages in `require` in place of deptrac's, and a look at each format's candidate backend — nette/neon's positioned nodes, nikic/php-parser's format-preserving printer — against [the distribution rule](../distribution.md) that a family needing a tool's own code moves to its own package.

**Built 2026-09-30**: `YamlTree` over `YamlLines`, which keeps each line's own ending and a byte-order mark, so joining the lines gives the document back byte for byte; the node types `YamlMapping`, `YamlEntry`, `YamlSequence`, `YamlItem` and `YamlValue`, each value with its kind, its source text, its closing comment, its extent and its decoded meaning; `YamlTreeParser`; and `YamlScalarDecoder` with its `YamlDecoding` layer.
Checked against the same corpus after the port: the reader decodes all 385 valid files it accepts exactly as symfony/yaml does.

## Built 2026-09-30 — the writer: narrow edits, new content in the target's own style

**What.** `Formats/Yaml/YamlTreeWriter` makes seven edits over a located tree, string in and string out like `JsonObjectWriter`: replace a single-line scalar, keeping or dropping the comment after it; replace an entry's value; add an entry to a mapping, or to a key holding nothing; insert an item before another or after the last; append a scalar to a block sequence or a one-line flow sequence; and remove an item or an entry with the comment lines directly above it, the dash moving to an item's next key when the removed key opened it.
An edit the document's shape does not allow is refused, naming the path; removing a mapping's only entry or a sequence's only item is refused too, since what holds it is what should go.

**New content comes from a `YamlFragment`**: an entry, an item or a value taken from a source document, or a scalar entry or item built from its text.
It is written in the target document's `YamlStyle`, read from the document itself as the unit a nested mapping steps in by, where a sequence's dashes stand against their key, and where an item's content starts after its dash, each the one the document uses most; an inserted item takes its own sequence's dash style where the sequence shows one.
Nesting steps in by the target's unit rather than the source's, while the further lines of a scalar are shifted with their owner and never rescaled, so a script's own indentation reaches the target unchanged; the comment lines directly above an entry or an item travel with it, and blank lines inside it stay.

**The writer never takes a line break away.** Inserted lines end in the document's line ending, and a last line without one gains it when anything is inserted after it; a removal leaves the remaining lines' endings as they were.
Found by the corpus run below: keeping a document without a final line break that way, as the first cut did, inserted a template's `run: |` script without its final line break when it became the document's last value, a value sync could never make match the template's.

**Checked** over the same corpus with the production classes: 16,798 edits across nine kinds — adding a key to a job, removing each job key, inserting a step with a nested `with` and a block scalar and a plain `run` step at the start, middle and end of every job, removing each step, raising every action ref, adding an input to every `with` and a `with` block to every step without one, and adding a pattern to every trigger filter — each parsing to exactly the expected data and touching no line outside the edit, with four refusals, all designed: three mappings holding only the entry to remove, one filter written as a string.

## Built 2026-09-30 — the rule's core: keys, jobs, steps and exact values

**What.** `Rules/GitHub/Workflow/GitHubWorkflow` over four supporting classes: `DeclaredWorkflow` holds the template and refuses at construction a step without an id, a step id its job declares twice, or a workflow the reader refuses; `WorkflowContainment` walks the declared workflow against the project's and finds the first place the project falls short; `WorkflowDifference` carries that shortfall's explanation beside the edit that resolves it; `WorkflowSyntax` holds the parts of GitHub's syntax the walk reads — where jobs and their steps are, and what names a step.
`apply()` resolves the first difference and walks again until none is left, so a second sync finds nothing and returns its input; the passes are bounded by the declared workflow's size, and running out of them is an engine bug by construction.
An absent file is written as the template reads, comments included.

**How the walk compares.** A declared key must be present, and a key declared with nothing under it asks for the key alone; a value the project left empty takes the declared one whole.
A job's steps are found by id in declared order: a missing step goes in after the declared step before it, or first; a step without an id that holds the declared step, its id aside, is adopted and gains the id as its last key, since a step's key order means nothing; a declared step found before its predecessor is refused (decision 9).
A list of scalars must hold the declared items, a missing one appended; every other value is exact for now, action refs included, and a single-line scalar is corrected in place with the template's text while anything else has its value replaced.
A shape sync cannot edit into the declared form — a list where a mapping is declared — is refused through the writers' existing `RuntimeException`, so no new refusal mechanism was needed.

**What this cut leaves to the next ones**: minimum versions (decision 6), weakening additions and trigger filters (decisions 2 and 3, where an added filter key is still the project's here), GitHub's equivalent forms (decision 5), and the lock, which is what lets a step the standard stops declaring be retracted.
A standard may also want to ship a workflow as a template the project completes with filters of its own; the filter decision leaves room for the standard to mark a trigger as the project's to filter, designed when a standard needs it.
