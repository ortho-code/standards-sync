<!-- Generated from the rule library and scenario suite — do not edit. Regenerate: composer app-generate-rule-catalog -->

# GitHubWorkflow

Keeps a declared GitHub Actions workflow in a project's workflow file: every key, job and step it declares is there, and whatever the project adds stays. A missing key, job or step is added — a step after the one declared before it — and a declared value the project changed is written back; the file's own formatting and comments stay as they are. Every declared step carries an id; a project's step without one that already holds a declared step is taken as that step and gains its id. An absent file is written as the workflow is declared, comments included.

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
