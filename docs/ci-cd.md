---
layout: default
title: CI/CD
description: Run MicroUnit on GitHub Actions, GitLab CI or any other pipeline, with annotations, reports, coverage gates and integrity checks.
---

# CI/CD

MicroUnit is built to run in a pipeline without ceremony. It needs nothing but PHP, it knows when it's running in CI (no browser tabs, no snapshot files written, failures annotated on GitHub), and its exit code tells the pipeline everything it needs to know. Getting from "works on my machine" to "green check on every pull request" is usually one step in a workflow file. This page covers GitHub Actions, GitLab CI and a generic setup, plus the stricter checks worth switching on once the basics run: a coverage minimum, failing on tests without assertions, and catching tests that were quietly weakened in a pull request.

## What MicroUnit does differently in CI

Most CI systems set `CI=true`, and MicroUnit reacts to it:

- **The Test Explorer never opens.** It is opt-in, and even with `withTestExplorer()` it only opens in interactive terminals, never when `CI` is set.
- **Snapshots are never created.** A missing snapshot fails the test instead of being written (more [below](#snapshots-in-ci)).
- **GitHub Actions annotations** appear automatically when `GITHUB_ACTIONS=true`.
- **Colours stay on** in the logs of GitHub Actions, GitLab CI, Azure Pipelines, CircleCI, Buildkite, TeamCity and Drone, even though output isn't a terminal there. Elsewhere, piped output is plain. Force either way with `--colors` / `--no-colors` or the `FORCE_COLOR` / `NO_COLOR` environment variables.

## Exit codes

The pipeline only cares about one number:

| Code | Meaning |
| --- | --- |
| `0` | All tests passed. |
| `1` | Tests failed, coverage is below the minimum, or a strict check you enabled failed (risky tests, weakened tests). |
| `2` | Usage or configuration error: an unknown option, a broken config file, a missing test directory. |

Skipped tests don't change the exit code.

## GitHub Actions

A complete workflow for a typical project:

```yaml
name: Tests

on:
  push:
    branches: ["main"]
  pull_request:

permissions:
  contents: read

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          coverage: none

      - run: composer install --no-interaction --prefer-dist

      - run: vendor/bin/microunit --parallel
```

That's it. Failing tests show up as annotations on the failing line, right in the pull request's "Files changed" tab, and on the run's summary page. No reporter action, no extra config. If you'd rather not have them, `withoutCiAnnotations()` turns them off.

`coverage: none` is not a mistake, even if you measure coverage. MicroUnit measures coverage itself, in pure PHP, so it needs neither Xdebug nor PCOV. Leaving them out keeps every test run faster.

For a real-world reference, MicroUnit's own workflow ([`.github/workflows/ci.yml`](https://github.com/mitarnik04/MicroUnit/blob/main/.github/workflows/ci.yml)) runs the suite on Ubuntu, Windows and macOS, once sequentially and once with `--parallel=4`, so problems that only appear in one mode get caught.

### Reports as artifacts

Add the report writers to your config:

```php
use MicroUnit\Output\HtmlTestWriter;
use MicroUnit\Output\JUnitTestWriter;
use MicroUnit\Output\MinimalStringTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestWriters(
        new MinimalStringTestWriter(),
        new JUnitTestWriter('build/junit.xml'),
        new HtmlTestWriter('build/test-report.html'),
    )
    ->build();
```

And upload them, also when the tests failed (that's when you want them most):

```yaml
      - run: vendor/bin/microunit --parallel

      - uses: actions/upload-artifact@v4
        if: always()
        with:
          name: test-reports
          path: build/
```

The HTML report is a single self-contained file, readable by anyone who downloads the artifact. `junit.xml` is what JUnit reporter actions read if you want results rendered as a check run. Details on both in [Output and reports](output.md).

### Coverage with a minimum

`withCoverage()` measures coverage and writes reports; `minimum` makes the run fail (exit code `1`) when line coverage drops below it:

```php
->withCoverage(include: 'src', clover: 'build/clover.xml', minimum: 80)
```

```
Code coverage
  Lines:      76.4% (1203/1575)
  Functions:  81.0% (162/200)
  ...
  Line coverage 76.4% is below the required minimum of 80.0%.
```

Coverage configured this way runs on every run, also locally. If that's too slow for daily work, either pass `--no-coverage` locally, or enable coverage only in CI. The config is plain PHP, so that's an `if`:

```php
$config = MicroUnitConfigBuilder::create()
    ->withTestDir('tests');

if (getenv('CI')) {
    $config->withCoverage(include: 'src', cobertura: 'build/cobertura.xml', minimum: 80);
}

return $config->build();
```

Disable OPcache for the CLI in coverage runs, since it can bypass MicroUnit's instrumentation (MicroUnit warns if it's on):

```yaml
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          coverage: none
          ini-values: opcache.enable_cli=0
```

Reports, formats and how the measurement works: see [Code coverage](coverage.md).

### Parallel runs

`--parallel` starts one worker per CPU core; `--parallel=4` sets the number. To make it the default everywhere, use `runInParallel()` in the config. Coverage works in parallel runs too. See [Parallel runs](parallel.md).

### Integration tests with real services

Tests that use `Database::connect()` skip themselves when the database is unreachable, which is nice on a laptop and dangerous in CI, where a missing service would turn into a quietly green run. Give the job the service it needs:

```yaml
jobs:
  tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:17
        env:
          POSTGRES_USER: shop
          POSTGRES_PASSWORD: secret
          POSTGRES_DB: shop_test
        ports: ["5432:5432"]
        options: >-
          --health-cmd pg_isready
          --health-interval 5s
          --health-retries 10
    env:
      DB_DSN: "pgsql:host=127.0.0.1;port=5432;dbname=shop_test"
    steps:
      # checkout, setup-php (with extensions: pdo_pgsql), composer install, then:
      - run: vendor/bin/microunit
```

More on fixtures and skipping in [Integration testing](integration-testing.md).

## The integrity check for pull requests

Here's a failure mode that every team has seen at least once: a test goes red, and instead of fixing the code, someone (or some AI agent) "fixes" the test. An assertion disappears. An expected value changes to whatever the code currently returns. A test gets a skip. The run is green and the bug ships.

MicroUnit's integrity check compares your tests with a git revision and lists exactly those changes: removed tests, removed assertions, new skips, changed expected values, removed data sets and assertions that can't fail. Each finding asks for a look; none of them proves a mistake. Sometimes an expected value really should change. But a reviewer should see it.

In a pull request, compare against the target branch. The check needs that branch's history, and `actions/checkout` fetches a single commit by default, so fetch everything:

```yaml
on:
  pull_request:

jobs:
  integrity:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/microunit integrity origin/${{ github.base_ref }}
```

`microunit integrity` exits with `1` when it finds something and `0` when it doesn't. If the revision doesn't exist (a shallow clone, a typo), it stops with exit code `2` and says so.

The alternative is to run the check as part of every test run, configured in PHP:

```php
->withIntegrityCheck('origin/main', failOnFindings: true)
```

The findings are printed after the results, and with `failOnFindings: true` they fail the run. A revision that isn't available (a shallow clone without the target branch) fails the run as well, with a message on standard error, so make sure the pipeline fetches it.

The check is opt-in everywhere: it runs only where you configure it or call the command. See [AI coding agents](ai-agents.md) for using it while an agent works on your code.

## Fail on risky tests

A test that passes without making a single assertion proves nothing. MicroUnit marks these as `no assertions` in the output; in CI you can make them fail the run:

```php
->failOnRiskyTests()
```

```
[✔] syncs_the_inventory (0.02ms) no assertions

Summary: 41/41 passed, 0 failed in 0.84s
1 test(s) passed without a single assertion (failOnRiskyTests).
```

The tests themselves still show as passed; the run exits with `1`. Assertions made through framework helpers (Laravel's `assertOk()`, Symfony's `assertResponseIsSuccessful()`, ...) count, see [Laravel, Symfony and CodeIgniter](frameworks.md).

## Snapshots in CI

Locally, the first run of a snapshot assertion writes the snapshot file, and you review and commit it. In CI that would be wrong: a snapshot created in a pipeline compares the code against itself and always passes. So when `CI` (or Azure Pipelines' `TF_BUILD`) is set, a missing snapshot fails the test:

```
Failed asserting that the snapshot matches: there is no snapshot at /home/runner/work/shop/shop/tests/__snapshots__/invoice-tests/renders_the_invoice.snap (they are never created in CI; run the tests locally and commit the file).
```

The fix is always the same: run the tests locally and commit the `__snapshots__` directory. Never pass `--update-snapshots` in a pipeline. More in [Snapshots](snapshots.md).

## GitLab CI

```yaml
tests:
  image: php:8.5-cli
  before_script:
    - apt-get update && apt-get install -y git unzip
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-interaction --prefer-dist
  script:
    - vendor/bin/microunit --parallel
  coverage: '/Lines:\s+\S*?(\d+\.\d+)%/'
  artifacts:
    when: always
    paths:
      - build/test-report.html
    reports:
      junit: build/junit.xml
      coverage_report:
        coverage_format: cobertura
        path: build/cobertura.xml
```

With `JUnitTestWriter('build/junit.xml')` configured, GitLab shows the results in the merge request's test report widget. With `withCoverage(cobertura: 'build/cobertura.xml')`, it marks covered and uncovered lines in the merge request diff, and the `coverage` regex picks the line percentage out of MicroUnit's console summary. (The `\S*?` skips the colour code in front of the number, because GitLab logs keep colours.)

For merge requests, the integrity check needs the target branch, which GitLab's shallow clone may not include:

```yaml
integrity:
  image: php:8.5-cli
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
  before_script:
    # same as above
  script:
    - git fetch origin "$CI_MERGE_REQUEST_TARGET_BRANCH_NAME"
    - vendor/bin/microunit integrity "origin/$CI_MERGE_REQUEST_TARGET_BRANCH_NAME"
```

## Any other CI system

Jenkins, Azure Pipelines, CircleCI, Bitbucket Pipelines, Buildkite, a shell script on a build server: they all work the same way.

```sh
composer install --no-interaction --prefer-dist
vendor/bin/microunit --parallel
```

The exit code decides the build. For results in the CI's UI, configure `JUnitTestWriter`, since practically every CI system reads JUnit XML. For a report people can open, add `HtmlTestWriter` and publish the file as an artifact.

If your system doesn't set `CI` (most do), set it yourself. That keeps snapshots from being created on the build machine.

## Caching

Cache Composer's dependencies as you normally would. Don't bother caching the `.microunit` directory. It holds run logs, Test Explorer state and caches (coverage instrumentation, compiled bytecode for parallel workers) that MicroUnit rebuilds on the first run, and it's ignored by git anyway. A fresh one per pipeline costs next to nothing.

## Related

- [Output and reports](output.md): every writer and its options.
- [Code coverage](coverage.md): reports, formats and how the measurement works.
- [Parallel runs](parallel.md): how work is split across processes.
- [Command line](cli.md): every flag.
- [Configuration](configuration.md): every option on `MicroUnitConfigBuilder`.
