<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# GitHubWorkflow

Keeps a declared GitHub Actions workflow in a project's workflow file: every key, job and step it declares is there, and whatever the project adds stays. A missing key, job or step is added — a step after the one declared before it — and a declared value the project changed is written back; the file's own formatting and comments stay as they are. Every declared step carries an id; a project's step without one that already holds a declared step is taken as that step and gains its id. A key, job, step or list item a standard declared at an earlier sync and declares no longer is taken out, with whatever the project added inside it. An absent file is written as the workflow is declared, comments included.

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new GitHubWorkflow(
            target: FileTarget::fromString('.github/workflows/checks.yml'),
            workflow: FileContent::fromString(
                <<<'YAML'
                    name: Checks

                    on:
                      pull_request:
                      push:
                        branches:
                          - main

                    jobs:
                      checks:
                        runs-on: ubuntu-26.04
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          # setup-php before the install
                          - id: setup-php
                            uses: shivammathur/setup-php@v2
                            with:
                              php-version: '8.5'
                              coverage: none
                          - id: install
                            uses: ramsey/composer-install@v4
                          - id: checks
                            run: composer app-checks
                    YAML,
            ),
            replacesBlock: Label::fromString('acme'),
        ));
    }
});
```

…which reports as: *Keeps the declared workflow in .github/workflows/checks.yml, beside any keys, jobs and steps the project adds.*

## A project without the workflow gets it as declared, comments included

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/creates-the-workflow`](../../../tests/Scenario/GitHub/Workflow/fixtures/creates-the-workflow)

**Creates** `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

`README.md` **stays byte-identical**:

```
# A project without the workflow yet
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## The project's own triggers, keys, inputs, steps and jobs stay beside the declared ones

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/keeps-the-projects-additions`](../../../tests/Scenario/GitHub/Workflow/fixtures/keeps-the-projects-additions)

`.github/workflows/checks.yml` **stays byte-identical**:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main
      - develop
  workflow_dispatch:

jobs:
  checks:
    runs-on: ubuntu-26.04
    timeout-minutes: 15
    steps:
      - id: checkout
        name: Check out the code
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
          extensions: intl
      - id: install
        uses: ramsey/composer-install@v4
      - name: Warm the cache
        run: bin/console cache:warmup
      - id: checks
        run: composer app-checks
  e2e:
    runs-on: ubuntu-26.04
    steps:
      - run: make e2e
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A declared value the project changed is written back

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/corrects-a-changed-value`](../../../tests/Scenario/GitHub/Workflow/fixtures/corrects-a-changed-value)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A missing step is inserted after the one declared before it

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/inserts-a-missing-step-after-its-predecessor`](../../../tests/Scenario/GitHub/Workflow/fixtures/inserts-a-missing-step-after-its-predecessor)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A missing job is added whole

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/adds-a-missing-job`](../../../tests/Scenario/GitHub/Workflow/fixtures/adds-a-missing-job)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  e2e:
    runs-on: ubuntu-26.04
    steps:
      - run: make e2e
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  e2e:
    runs-on: ubuntu-26.04
    steps:
      - run: make e2e
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A missing key of a job is added to it

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/adds-a-missing-job-key`](../../../tests/Scenario/GitHub/Workflow/fixtures/adds-a-missing-job-key)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
    runs-on: ubuntu-26.04
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A declared step the project edited is written back rather than duplicated

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/writes-back-an-edited-declared-step`](../../../tests/Scenario/GitHub/Workflow/fixtures/writes-back-an-edited-declared-step)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks --verbose
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A workflow synced as a managed block loses its markers and its steps gain their ids

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/takes-over-the-managed-block`](../../../tests/Scenario/GitHub/Workflow/fixtures/takes-over-the-managed-block)

**Before** — `.github/workflows/checks.yml`:

```yaml
# >>> acme - managed >>>
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - uses: actions/checkout@v7
      # setup-php before the install
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - uses: ramsey/composer-install@v4
      - run: composer app-checks
# <<< acme <<<
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - uses: actions/checkout@v7
        id: checkout
      # setup-php before the install
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
        id: setup-php
      - uses: ramsey/composer-install@v4
        id: install
      - run: composer app-checks
        id: checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A step without an id that holds a declared step is taken as that step and gains its id

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/adopts-a-step-that-holds-the-declared-one`](../../../tests/Scenario/GitHub/Workflow/fixtures/adopts-a-step-that-holds-the-declared-one)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
          extensions: intl
      - uses: ramsey/composer-install@v4
      - run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - uses: actions/checkout@v7
        id: checkout
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
          extensions: intl
        id: setup-php
      - uses: ramsey/composer-install@v4
        id: install
      - run: composer app-checks
        id: checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A step without an id on an older version of the declared action is taken as that step, and its action raised

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/adopts-a-step-on-an-older-action`](../../../tests/Scenario/GitHub/Workflow/fixtures/adopts-a-step-on-an-older-action)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v1
        with:
          php-version: '8.5'
          coverage: none
          extensions: intl
      - uses: ramsey/composer-install@v4
      - run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - uses: actions/checkout@v7
        id: checkout
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
          extensions: intl
        id: setup-php
      - uses: ramsey/composer-install@v4
        id: install
      - run: composer app-checks
        id: checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A newer action and a newer runner stay, since versions are minimums

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/keeps-a-newer-action-and-runner`](../../../tests/Scenario/GitHub/Workflow/fixtures/keeps-a-newer-action-and-runner)

`.github/workflows/checks.yml` **stays byte-identical**:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-28.04
    steps:
      - id: checkout
        uses: actions/checkout@v8
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2.36.0
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## An action below its declared version is raised, the project's comment kept

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/raises-an-older-action`](../../../tests/Scenario/GitHub/Workflow/fixtures/raises-an-older-action)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v6 # the one we tested
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7 # the one we tested
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A digest pin whose comment names the declared version or later stays as written

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/keeps-a-digest-pin-at-the-minimum`](../../../tests/Scenario/GitHub/Workflow/fixtures/keeps-a-digest-pin-at-the-minimum)

`.github/workflows/checks.yml` **stays byte-identical**:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.0.1
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A digest pin below the declared version is replaced, its comment with it

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/replaces-a-digest-pin-below-the-minimum`](../../../tests/Scenario/GitHub/Workflow/fixtures/replaces-a-digest-pin-below-the-minimum)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v6.0.2
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A branch ref names no version, so the declared ref replaces it

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/replaces-a-branch-ref`](../../../tests/Scenario/GitHub/Workflow/fixtures/replaces-a-branch-ref)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@main
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A moving runner label names no version, so the declared label replaces it

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/replaces-a-moving-runner-label`](../../../tests/Scenario/GitHub/Workflow/fixtures/replaces-a-moving-runner-label)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-latest
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A step the lock records and the standard no longer declares is taken out, with what the project added to it

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/retracts-a-step-no-longer-declared`](../../../tests/Scenario/GitHub/Workflow/fixtures/retracts-a-step-no-longer-declared)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: lint
        name: Lint the workflows
        run: composer app-lint
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/lint",
                "/jobs/checks/steps/lint/id",
                "/jobs/checks/steps/lint/run",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A job the lock records and the standard no longer declares is taken out, the project's own jobs staying

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/retracts-a-job-no-longer-declared`](../../../tests/Scenario/GitHub/Workflow/fixtures/retracts-a-job-no-longer-declared)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
  lowest:
    runs-on: ubuntu-26.04
    steps:
      - id: tests
        run: composer app-run-tests
  e2e:
    runs-on: ubuntu-26.04
    steps:
      - run: make e2e
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
  e2e:
    runs-on: ubuntu-26.04
    steps:
      - run: make e2e
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run",
                "/jobs/lowest",
                "/jobs/lowest/runs-on",
                "/jobs/lowest/runs-on/ubuntu-26.04",
                "/jobs/lowest/steps",
                "/jobs/lowest/steps/tests",
                "/jobs/lowest/steps/tests/id",
                "/jobs/lowest/steps/tests/run"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## A list item the lock records and the standard no longer declares is taken out, the project's own items staying

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/retracts-a-list-item-no-longer-declared`](../../../tests/Scenario/GitHub/Workflow/fixtures/retracts-a-list-item-no-longer-declared)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main
      - master
      - develop

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main
      - develop

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/on/push/branches/master",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

## An event whose declared filter is retired goes back to the event the standard declares now

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/returns-an-event-to-no-filters`](../../../tests/Scenario/GitHub/Workflow/fixtures/returns-an-event-to-no-filters)

**Before** — `.github/workflows/checks.yml`:

```yaml
name: Checks

on:
  pull_request:
    branches:
      - main
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**After:**

```yaml
name: Checks

on:
  pull_request:
  push:
    branches:
      - main

jobs:
  checks:
    runs-on: ubuntu-26.04
    steps:
      - id: checkout
        uses: actions/checkout@v7
      # setup-php before the install
      - id: setup-php
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - id: install
        uses: ramsey/composer-install@v4
      - id: checks
        run: composer app-checks
```

**Before** — `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/pull_request/branches",
                "/on/pull_request/branches/main",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

**After:**

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/checks.yml": {
            "workflow": [
                "/name",
                "/on",
                "/on/pull_request",
                "/on/push",
                "/on/push/branches",
                "/on/push/branches/main",
                "/jobs",
                "/jobs/checks",
                "/jobs/checks/runs-on",
                "/jobs/checks/runs-on/ubuntu-26.04",
                "/jobs/checks/steps",
                "/jobs/checks/steps/checkout",
                "/jobs/checks/steps/checkout/id",
                "/jobs/checks/steps/checkout/uses",
                "/jobs/checks/steps/setup-php",
                "/jobs/checks/steps/setup-php/id",
                "/jobs/checks/steps/setup-php/uses",
                "/jobs/checks/steps/setup-php/with",
                "/jobs/checks/steps/setup-php/with/php-version",
                "/jobs/checks/steps/setup-php/with/coverage",
                "/jobs/checks/steps/install",
                "/jobs/checks/steps/install/id",
                "/jobs/checks/steps/install/uses",
                "/jobs/checks/steps/checks",
                "/jobs/checks/steps/checks/id",
                "/jobs/checks/steps/checks/run"
            ]
        }
    }
}
```

Declared as:

```php
return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new GitHubWorkflow(
            target: FileTarget::fromString('.github/workflows/deploy.yml'),
            workflow: FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                      push:

                    jobs:
                      deploy:
                        needs: build
                        if: github.event_name == 'push'
                        runs-on: ubuntu-26.04
                        environment: production
                        steps:
                          - id: deploy
                            run: make deploy
                    YAML,
            ),
        ));
    }
});
```

…which reports as: *Keeps the declared workflow in .github/workflows/deploy.yml, beside any keys, jobs and steps the project adds.*

## GitHub's other spellings of the declared triggers, needs, runner, environment and condition hold them, with the project's own additions beside them

Fixture: [`tests/Scenario/GitHub/Workflow/fixtures/reads-githubs-equivalent-spellings`](../../../tests/Scenario/GitHub/Workflow/fixtures/reads-githubs-equivalent-spellings)

`.github/workflows/deploy.yml` **stays byte-identical**:

```yaml
on: [pull_request, push, workflow_dispatch]

jobs:
  build:
    runs-on: ubuntu-26.04
    steps:
      - run: make
  deploy:
    needs: [build, lint]
    if: ${{ github.event_name == 'push' }}
    runs-on: [ubuntu-28.04]
    environment:
      name: production
      url: https://acme.example
    steps:
      - id: deploy
        run: make deploy
  lint:
    runs-on: ubuntu-26.04
    steps:
      - run: make lint
```

**Creates** `standards-sync.lock`:

```
{
    "_readme": [
        "Written by standards-sync: the entries the standards declared at the last sync, so the next sync can retract any they stop declaring.",
        "Commit this file; do not edit it."
    ],
    "files": {
        ".github/workflows/deploy.yml": {
            "workflow": [
                "/on",
                "/on/pull_request",
                "/on/push",
                "/jobs",
                "/jobs/deploy",
                "/jobs/deploy/needs",
                "/jobs/deploy/needs/build",
                "/jobs/deploy/if",
                "/jobs/deploy/runs-on",
                "/jobs/deploy/runs-on/ubuntu-26.04",
                "/jobs/deploy/environment",
                "/jobs/deploy/environment/name",
                "/jobs/deploy/steps",
                "/jobs/deploy/steps/deploy",
                "/jobs/deploy/steps/deploy/id",
                "/jobs/deploy/steps/deploy/run"
            ]
        }
    }
}
```
