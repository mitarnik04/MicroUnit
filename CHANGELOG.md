# Changelog

All notable changes to MicroUnit. The format follows [Keep a Changelog](https://keepachangelog.com/),
and MicroUnit uses [semantic versioning](https://semver.org/).

## [1.0.0] - 2026-11-19

The first stable release. MicroUnit started before AI coding assistants existed and got as far as a
public beta; building the rest next to a day job was more than one person could do. With AI as my
tool I finally had the capacity to bring it to this release: to test every part of it properly,
down to running the real test suites of well-known PHP projects through it, and to build every
feature I had imagined for it.

### Highlights

- **Faster than PHPUnit** in all 24 comparisons on the test suites of six popular projects (up to 5×
  on a full suite), with reproducible benchmarks in `benchmarks/` (method, raw data and every
  exclusion published).
- **Built for AI coding agents:** compact JSON output with only the failures, a test integrity check
  that catches weakened tests, and an installable agent skill.
- **`microunit migrate`** converts PHPUnit suites, including mocks, data providers, base classes and
  expected exceptions.
- **Test Explorer** in the browser, with Visual Studio style run modes.
- **Code coverage in pure PHP** (no Xdebug, no PCOV) and **built-in parallel runs**, still with zero
  dependencies.
- **Laravel, Symfony and CodeIgniter** integrations.

### Added

- Writing tests: named fixtures (per test or shared per suite, with cleanup, depending on each other,
  available to setUp/tearDown, replaceable by reference), `setUpOnce`/`tearDownOnce`, data sets as
  plain keyed arrays or generators in `defineGroup()`, skipped tests (`Assert::skip`, `skipIf`).
- Assertions: `isType`, `equivalent`, `containsSubset`, `hasProperty`, `each`, `containsAll`,
  `isUnique`, `isSorted`, `isList`, `sameSize`, `equalsWithinPercent`, `isNan`, `isFinite`,
  `dateEquals`, `stringContainsIgnoringCase`, `equalsIgnoringCase`, `equalsIgnoringWhitespace`,
  `matchesFormat`, `isJson`, `jsonEquals`, `fileExists`, `fileNotExists`, `directoryExists`,
  `fileContains`, `fileContentEquals`, `doesNotThrow`, `triggersError`, `prints`, `eventually`,
  `multiple` (soft assertions), `matchesSnapshot`, plus `stringStartsWith`, `stringEndsWith`,
  `matchesRegex`, `equalsWithDelta`, `isGreaterThanOrEqual`, `isLessThanOrEqual`, `fail`, and an
  optional failure message on every assertion. `AssertResponse` for HTTP responses.
- Snapshot testing with `--update-snapshots`; snapshots are never created in CI.
- Mocks: per-instance call logs (next to the all-instances log), `MicroMock::of($instance)`,
  changing return values after `build()`, `returnsMap`, partial mocks with `interceptOnly`.
- Test Explorer (opt-in: `microunit explorer`, `--explorer` or `withTestExplorer()`): live browser
  view; run all, failed, selected (multi-select), a suite, a group, tests in view, not-run or passed
  tests; repeat the last run; open in your editor; fully responsive. A run without it does no work
  for it.
- Code coverage: text, HTML, Clover and Cobertura reports, minimum coverage, cached instrumentation.
- Parallel runs (`--parallel[=N]`) that keep every suite in one worker and give each worker a
  `TEST_TOKEN` for Laravel's parallel testing support.
- Framework integrations for Laravel, Symfony and CodeIgniter 4, with per-suite traits and test cases.
- Integration testing toolkit: SQLite/PDO database helper, built-in HTTP server and client,
  temporary directories.
- Output: HTML report, JUnit XML, GitHub Actions annotations, log files (text and JSON lines), agent
  JSON; several writers at once.
- AI agents: `--agent` output (automatic under Claude Code or with `MICROUNIT_AGENT=1`),
  `microunit integrity [revision]` and `withIntegrityCheck()`, `failOnRiskyTests()`, warnings for
  tests that pass without assertions, `microunit init --skill`.
- CLI: `init`, `migrate`, `integrity`, `explorer`, `help <command>`, `--list[=json]`, `--failed`,
  `--group`, `--filter` with substrings or regular expressions.
- Configuration: test groups, bootstrap file, runtime directory, and every new feature above.
- A benchmark harness and its published results in `benchmarks/`.
- Documentation rewritten from scratch, one page per feature.

### Changed (breaking)

- PHP 8.5 or newer is required (was 8.0); PHP 8.6 is supported and tested.
- Argument order now follows PHPUnit everywhere: `Assert::isGreaterThan($minimum, $actual)` and
  `Assert::isLessThan($maximum, $actual)` swapped their arguments.
- `Assert::throws()` returns the caught exception and can check its message;
  `Assert::contains()` accepts any iterable and its third parameter is called `$strict`;
  `Assert::empty()`/`notEmpty()` accept any value.
- Coloured console output is the default (`NO_COLOR`, `FORCE_COLOR` and `--no-colors` are honoured).
- The run time in the summary is wall-clock time (it used to be the sum of the test bodies).
- The command is `vendor/bin/microunit`; `bin/run-tests.php` remains as a shim.
- Runtime files (logs, caches, explorer state) live in `.microunit/` and ignore themselves in git.
- Internal classes were reorganised; `ConfigProvider`, `TesterHub`, the old cache classes and the
  string helpers are gone.

## [0.x] - beta

The public beta: closures-based tests, the first assertions, mocking and output writers.
