---
layout: default
title: Configuration
description: Every option of microunit.config.php and MicroUnitConfigBuilder, grouped by what it controls.
---

# Configuration

MicroUnit is configured in PHP, in a file called `microunit.config.php` that returns a `MicroUnitConfig`. You build that object with `MicroUnitConfigBuilder`. No XML, no YAML, no schema to look up: your editor autocompletes the options, and because it is code, you can read environment variables or branch on them. Command line flags exist only to change a single run; anything you want every time belongs here. This page lists every builder method, grouped by what it controls.

## A complete example

```php
<?php

use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\JUnitTestWriter;
use MicroUnit\Output\MinimalStringTestWriter;

$config = MicroUnitConfigBuilder::create()
    ->withTestGroup('unit', 'tests/Unit')
    ->withTestGroup('integration', 'tests/Integration')
    ->withTestFilePatterns('*Test.php')
    ->withBootstrapFile('tests/bootstrap.php')
    ->runInParallel()
    ->withTestExplorer(editor: 'phpstorm')
    ->failOnRiskyTests();

// Coverage, a JUnit report and the integrity check only in CI, where nobody is waiting.
if (getenv('CI') !== false) {
    $config
        ->withTestWriters(
            new MinimalStringTestWriter(),
            new JUnitTestWriter(__DIR__ . '/build/junit.xml'),
        )
        ->withCoverage(
            include: 'src',
            exclude: 'src/Legacy',
            html: 'build/coverage',
            clover: 'build/clover.xml',
            minimum: 80.0,
        )
        ->withIntegrityCheck('origin/main', failOnFindings: true);
}

return $config->build();
```

Builder methods change the builder and return it, so you can chain them, or call them conditionally on a variable like above. Do not forget `return` and `->build()` at the end; without them MicroUnit stops with exit code `2` and asks whether you forgot one of the two.

## The config file

MicroUnit looks for `microunit.config.php` in the current directory and then in each parent directory, and uses the first one it finds. So you can run `vendor/bin/microunit` from anywhere inside the project. `--config=path/to/file.php` (or `-c=...`) picks a file explicitly.

Without any config file, MicroUnit runs with the defaults: tests in `tests/` (or the current directory if there is no `tests/`), files named `*-tests.php`, compact console output, sequential.

**Paths in the config are relative to the config file**, never to the directory you run from. Absolute paths work too, so `__DIR__ . '/tests'` is fine. This applies to `withTestDir()`, `withTestGroup()`, `withBootstrapFile()`, `withCoverage()` and `withRuntimeDirectory()`.

The one exception is writer file paths, like `new JUnitTestWriter('build/junit.xml')`. Writers resolve relative paths against the current working directory. If you sometimes run the tests from a subdirectory, pass an absolute path with `__DIR__` to keep the report in one place.

## Defaults at a glance

| Setting | Default | Change it with |
| --- | --- | --- |
| Test directory | `tests/` next to the config file | `withTestDir()`, `withTestGroup()` |
| File patterns | `*-tests.php` | `withTestFilePatterns()`, `addTestFilePattern()` |
| Writers | `MinimalStringTestWriter` | `withTestWriters()`, `addTestWriter()` |
| Parallel workers | 1 (sequential) | `runInParallel()` |
| Coverage | off | `withCoverage()` |
| Test Explorer | off (opt-in), port 7357, links open VS Code | `withTestExplorer()` |
| GitHub Actions annotations | on when running on GitHub Actions | `withoutCiAnnotations()` |
| Agent output | automatic in agent sessions | `withAgentOutput()` |
| Integrity check | off | `withIntegrityCheck()` |
| Runtime directory | `.microunit` | `withRuntimeDirectory()` |

## Tests and discovery

### `withTestDir(string $testDirectory)`

The directory searched recursively for test files.

```php
->withTestDir('tests')
```

If you also define test groups, this directory belongs to no group: it runs when no `--group` is given.

### `withTestFilePatterns(string ...$patterns)`

Replaces the file name globs that mark a file as a test file. The default is `*-tests.php`.

```php
->withTestFilePatterns('*Test.php')
```

Globs are matched against the file name only, case-insensitively. Supported: `*`, `?`, `[abc]` and `[!abc]`.

### `addTestFilePattern(string $pattern)`

Adds one glob to the configured patterns, which start as the default `*-tests.php`.

```php
->addTestFilePattern('*Test.php') // finds *-tests.php and *Test.php
```

To replace the default, use `withTestFilePatterns()`.

### `withTestGroup(string $name, string $directory, string ...$filePatterns)`

Adds a named group of test files that you can run on its own with `--group=NAME`. Without `--group`, every group runs.

```php
->withTestGroup('unit', 'tests/Unit')
->withTestGroup('integration', 'tests/Integration', '*IntegrationTest.php')
```

Without file patterns, the group uses the configured test file patterns. Call it once per group. See [test groups](writing-tests.md#test-groups) for how groups combine with `withTestDir()`.

### `withBootstrapFile(string $bootstrapFile)`

A PHP file included once before any test file.

```php
->withBootstrapFile('tests/bootstrap.php')
```

You do not need it for autoloading; MicroUnit finds and loads your project's `vendor/autoload.php` on its own. Use it for environment variables, global helpers or framework setup that is not covered by an [integration](frameworks.md). A missing file is a configuration error (exit code `2`).

## Output

### `withTestWriters(ITestWriter ...$writers)`

Replaces the writers that report the results.

```php
use MicroUnit\Output\HtmlTestWriter;
use MicroUnit\Output\JUnitTestWriter;
use MicroUnit\Output\MinimalStringTestWriter;

->withTestWriters(
    new MinimalStringTestWriter(),
    new JUnitTestWriter(__DIR__ . '/build/junit.xml'),
    new HtmlTestWriter(__DIR__ . '/build/test-report.html'),
)
```

The writers that ship with MicroUnit:

| Writer | What it produces |
| --- | --- |
| `MinimalStringTestWriter` | One line per test, details only for failures. The default. |
| `StringTestWriter` | A detailed block per test with location, return value, captured output and stack traces. |
| `FileTestWriter` | The console output, without colours, written to a file. |
| `JUnitTestWriter` | JUnit XML for CI systems. |
| `HtmlTestWriter` | A self-contained HTML report, handy as a CI artifact. |
| `LogTestWriter` | A structured log with a configurable line format, or JSON lines. |
| `AgentTestWriter` | Compact JSON with the failures only, for AI agents. |
| `GitHubActionsTestWriter` | Annotations on the failing lines of a pull request. Added automatically on GitHub Actions. |

Their constructor options are on the [output and reports](output.md) page.

### `addTestWriter(ITestWriter $writer)`

Adds one writer to the configured ones, which start as the default console output.

```php
->addTestWriter(new JUnitTestWriter(__DIR__ . '/build/junit.xml')) // console output and a JUnit file
```

To replace the console output, use `withTestWriters()`.

### `withoutCiAnnotations()`

Turns off the GitHub Actions annotations that MicroUnit adds by itself when `GITHUB_ACTIONS=true`.

```php
->withoutCiAnnotations()
```

See [CI/CD](ci-cd.md) for what they look like.

## Running

### `runInParallel(?int $workers = null)`

Runs the tests in several worker processes. Without an argument, one worker per CPU core.

```php
->runInParallel()   // one worker per core
->runInParallel(4)  // exactly four workers
```

Whole suites are distributed, so a suite's `setUpOnce()` and shared fixtures stay in one process. With fewer than two suites, when `proc_open()` is disabled, or (one worker per core only) when the previous run suggests the tests finish in under a second, MicroUnit runs sequentially. Override per run with `--parallel[=N]` or `--no-parallel`. Details on the [parallel runs](parallel.md) page.

### `stopOnFailure()`

Stops the run after the first failing test.

```php
->stopOnFailure()
```

Usually more useful as a one-off: `vendor/bin/microunit --failed --stop-on-failure`.

## Coverage

### `withCoverage(...)`

Measures code coverage on every run. MicroUnit instruments the code itself, so you need neither Xdebug nor PCOV.

```php
->withCoverage(
    include: ['src', 'app'],
    exclude: 'src/Generated',
    html: 'build/coverage',
    clover: 'build/clover.xml',
    cobertura: 'build/cobertura.xml',
    text: true,
    minimum: 80.0,
)
```

| Parameter | Default | Purpose |
| --- | --- | --- |
| `string\|array $include` | `'src'` | Directories or files whose code is measured. |
| `string\|array $exclude` | `[]` | Directories or files inside `$include` to skip. |
| `?string $html` | `null` | Directory for the HTML report. |
| `?string $clover` | `null` | File for the Clover XML report (Codecov, Coveralls, Jenkins). |
| `?string $cobertura` | `null` | File for the Cobertura XML report (GitLab, Azure DevOps). |
| `bool $text` | `true` | Print a coverage table to the console after the run. |
| `?float $minimum` | `null` | Fail the run (exit code `1`) when line coverage is below this percentage. |

All paths are relative to the config file. Skip coverage for one run with `--no-coverage`; without `withCoverage()`, `--coverage` turns it on for one run with the defaults above. Coverage costs time, which is why the example at the top only enables it in CI. More on the [code coverage](coverage.md) page.

## Test Explorer

### `withTestExplorer(bool $openAfterRun = true, int $port = 7357, string $editor = 'vscode')`

Everything about the Test Explorer in one call. It is off by default: without this, `microunit explorer` and `--explorer` still open it, with the defaults.

```php
->withTestExplorer()                                        // open it after every interactive run
->withTestExplorer(port: 7400, editor: 'phpstorm')          // the same, on another port, with PhpStorm links
->withTestExplorer(openAfterRun: false, editor: 'cursor')   // only on demand, with Cursor links
```

- **`openAfterRun`**: open the explorer (or update the open one) after every local, interactive run. It never opens in CI or when the output is piped; `--no-explorer` keeps it closed for one run.
- **`port`**: the local port it listens on.
- **`editor`**: what its file links open. Known names: `vscode`, `cursor`, `windsurf`, `zed`, `phpstorm`, `idea`, `sublime`. Anything else must be a URL template containing `{file}` (and usually `{line}`), such as `myeditor://open?file={file}&line={line}`; an unknown name without `{file}` falls back to VS Code.

See [Test Explorer](test-explorer.md).

## Frameworks

### `withFramework(IFrameworkIntegration $framework)`

Boots a framework around every test, so tests run inside the framework's own test case with its database handling, fakes and helpers.

```php
use MicroUnit\Integration\Laravel\LaravelIntegration;

->withFramework(new LaravelIntegration(
    traits: [RefreshDatabase::class],
    environment: ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'],
))
```

MicroUnit ships `LaravelIntegration`, `SymfonyIntegration` and `CodeIgniterIntegration`. All three take `testCase` (the base test case, default: the framework's own), `traits` (applied to every test) and `environment` (like `<env>` in `phpunit.xml`). `microunit init` adds the right one when it finds the framework in `composer.json`. The [frameworks](frameworks.md) page has the details.

## AI agents and strictness

### `withAgentOutput(bool $enabled = true)`

Output for AI coding agents: one compact JSON object with only the failures, instead of the console writers.

```php
->withAgentOutput()        // always
->withAgentOutput(false)   // never, even in agent sessions
```

Without this setting it switches on by itself when an agent runs the tests: Claude Code sets `CLAUDECODE=1`, and any other agent can set `MICROUNIT_AGENT=1`. In agent mode the console writers step aside, while file writers such as JUnit keep working. Passing tests cost no tokens. Override per run with `--agent` or `--no-agent`. See [AI coding agents](ai-agents.md).

### `withIntegrityCheck(string $revision = 'HEAD', bool $failOnFindings = false)`

After every run, list tests that were weakened compared to a git revision: removed tests, removed assertions, new skips, changed expected values.

```php
->withIntegrityCheck()                                    // against HEAD, report only
->withIntegrityCheck('origin/main', failOnFindings: true) // e.g. in CI for pull requests
```

The check is opt-in: without this setting it never runs. With `failOnFindings: true`, findings make the run exit with `1`. When the check cannot compare (no git repository, or a revision missing from a shallow clone), MicroUnit says so on standard error, and with `failOnFindings: true` the run fails, so in CI make sure the revision is fetched. `microunit integrity [revision]` runs the same check on demand (see the [CLI](cli.md#integrity)).

### `failOnRiskyTests()`

Fails the run when a test passes without making a single assertion.

```php
->failOnRiskyTests()
```

Such tests are always marked `no assertions` in the output. With this setting they also make the run exit with `1`. Recommended when AI agents write tests, because a test that asserts nothing is the easiest way to look green.

## Runtime directory and logs

### `withRuntimeDirectory(string $directory)`

Where MicroUnit keeps its working files. The default is `.microunit` next to the config file.

```php
->withRuntimeDirectory('var/microunit')
```

It holds the run logs (`logs/`), the explorer's state (`explorer/`), caches for coverage instrumentation and parallel workers (`cache/`) and temporary files (`tmp/`). MicroUnit writes a `.gitignore` into it, so it never ends up in a commit and you do not need to add it to your own `.gitignore`.

### `persistRunLogs()`

Keeps the log of every run instead of only the latest one.

```php
->persistRunLogs()
```

PHP warnings, notices and deprecations during a run go to a log file in `logs/` inside the runtime directory instead of cluttering the console. By default each run deletes the previous logs; with this setting they pile up until you delete them.

## Building

### `MicroUnitConfigBuilder::create()` and `build()`

`create()` starts a builder with every setting at its default; `build()` returns the immutable `MicroUnitConfig`. Anything you did not set keeps its default.

You can also construct `MicroUnit\Config\MicroUnitConfig` directly with named arguments, but the builder is the documented interface, and its method names are what this page, the CLI help and the error messages refer to.

## Related

- [Command line](cli.md): flags that override these settings for one run.
- [Writing tests](writing-tests.md): where tests live, groups and patterns.
- [Output and reports](output.md), [code coverage](coverage.md), [parallel runs](parallel.md), [CI/CD](ci-cd.md).
