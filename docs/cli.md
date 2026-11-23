---
layout: default
title: Command line
description: Every microunit command and option, with exit codes and examples.
---

# Command line

Everything MicroUnit does starts with `vendor/bin/microunit`. Without a command it runs your tests; a handful of commands cover setup, migration, the integrity check and the Test Explorer.

Options only change the current run. Anything you want every time belongs in [`microunit.config.php`](configuration.md), which is why this page is short and the configuration page is not. The same information is built into the binary: `microunit help` gives the overview, `microunit help <command>` the details of one command.

```text
microunit [command] [options] [paths...]
```

## Commands

| Command | What it does |
| --- | --- |
| *(none)* | Run the tests (the default command, also called `run` in the help) |
| [`init`](#init) | Create `microunit.config.php` and a first test |
| [`migrate`](#migrate) | Convert PHPUnit tests to MicroUnit |
| [`integrity`](#integrity) | Find tests that were weakened since a git revision |
| [`explorer`](#explorer) | Open the Test Explorer with the latest results |
| [`help`](#help-and-version) | The overview, or `help <command>` for one command |

## Option syntax

Options that take a value need an equals sign: `--filter=Cart`, `--group=unit`, `--config=ci.config.php`. The short form works the same way: `-c=ci.config.php`. A space does not work: `--filter Cart` is read as the option without a value (an error) followed by a path called `Cart`.

Anything that does not start with `-` is a path. An unknown option is a usage error with exit code `2`.

## Running tests

```bash
vendor/bin/microunit
vendor/bin/microunit tests/Unit --filter=Cart
vendor/bin/microunit --failed --stop-on-failure
vendor/bin/microunit --parallel --coverage
```

### Selecting tests

| Option | Effect |
| --- | --- |
| `[paths...]` | Test files or directories instead of the configured ones |
| `--filter=PATTERN` | Tests whose `Suite::test` id contains PATTERN, or matches `/regex/` |
| `--group=NAME[,NAME]` | Only these test groups (`withTestGroup()`) |
| `--failed` | Only the tests that failed in the previous run |
| `--list[=json]` | List the selected tests instead of running them |

**Paths.** Relative to the current directory. A file runs even if its name does not match the configured file patterns; a directory is searched recursively with those patterns. When you pass paths, `--group` is ignored, because the paths already say what to run. A path that does not exist is a usage error.

**`--filter`** matches against test ids such as `Cart::sums_the_line_totals` or `Cart::applies_quantity_three items`. A plain pattern is a case-insensitive substring match. Wrap it in slashes for a regular expression, flags included:

```bash
vendor/bin/microunit --filter=discount                    # any test with "discount" in its id
vendor/bin/microunit --filter=Cart::                      # every test of the Cart suite
vendor/bin/microunit --filter='/^Cart::applies_quantity_/'
vendor/bin/microunit --filter='/refund|chargeback/i'
```

Quote regular expressions so your shell leaves them alone. An invalid one stops the run with an error.

**`--group`** takes one group name or several, separated by commas. Without it, every group runs. A name that is not configured is a usage error that lists the groups you do have. See [test groups](writing-tests.md#test-groups).

**`--failed`** re-runs exactly the tests that failed last time, by id. The previous results come from a small file in the runtime directory, written after every run, with or without the Test Explorer.

If nothing failed last time, MicroUnit says so and runs everything (or whatever `--filter` selects). When there are failures to re-run, `--filter` is not applied on top. Pairs well with `--stop-on-failure` while you work through a broken suite one test at a time.

**`--list`** prints the selected tests and exits with `0` without running anything. Test files, the bootstrap file and the framework are still loaded, because that is where tests get defined. Plain `--list` prints one id per line:

```text
Cart::starts_empty
Cart::sums_the_line_totals
Cart::applies_quantity_single item
```

`--list=json` prints one JSON object per line, which is what tools and agents want:

```json
{"id":"Cart::applies_quantity_single item","suite":"Cart","name":"applies_quantity_single item","group":"applies_quantity","file":"/app/tests/cart-tests.php","line":24}
```

In this output `group` is the base name of a `defineGroup()` data set test (`null` for tests from `define()`), not a test group from the config.

All selection options combine with each other and with `--list`, so `--list --group=integration --filter=Mailer` is a quick way to check what a run would pick up.

### Running

| Option | Effect |
| --- | --- |
| `--parallel[=N]` | N worker processes (default: one per CPU core) |
| `--no-parallel` | Run sequentially |
| `--stop-on-failure` | Stop after the first failing test |
| `--update-snapshots` | Rewrite the snapshots of the tests that run |
| `--config=FILE, -c` | Use this config file instead of finding `microunit.config.php` |

**`--parallel`** without a value starts one worker per CPU core; `--parallel=4` starts four. N must be a positive number. Whole suites are distributed across workers, and a run with fewer than two suites stays sequential.

Without a number, the run also stays sequential when the previous run suggests the tests finish in under a second, because starting the workers would cost more than it saves. A number forces workers even then (`--parallel=4`, or `runInParallel(4)` in the config), though for a run that short it is usually slower. `--no-parallel` overrides `runInParallel()` from the config. See [parallel runs](parallel.md).

**`--stop-on-failure`** ends the run after the first failing test, on top of whatever the config says.

**`--update-snapshots`** rewrites the stored snapshot of every `Assert::matchesSnapshot()` in the tests that run. Combine it with `--filter` so you only update what you meant to. See [snapshots](snapshots.md).

**`--config=FILE`** (or `-c=FILE`) loads that file instead of searching for `microunit.config.php` from the current directory upwards. The path is relative to the current directory, and paths inside the file are still relative to the file itself. A file that does not exist is a configuration error.

### Output

| Option | Effect |
| --- | --- |
| `--agent, --no-agent` | Compact JSON for AI agents (automatic in agent sessions) |
| `--colors, --no-colors` | Force coloured output on or off |
| `--coverage, --no-coverage` | Collect code coverage for this run, or skip it |
| `--explorer, --no-explorer` | Open the Test Explorer after this run, or keep it closed even when configured |

**`--agent`** replaces the console output with one JSON object containing only the failures. MicroUnit switches it on by itself when `CLAUDECODE=1` or `MICROUNIT_AGENT=1` is set; `--no-agent` forces normal output even then. File writers such as JUnit keep working either way. See [AI coding agents](ai-agents.md).

**`--colors`** and **`--no-colors`** override the detection, which otherwise turns colour off when output goes to a pipe or file and honours `NO_COLOR` and `FORCE_COLOR`. The flags win over those variables: `--colors` gives colour even when `NO_COLOR` is set.

**`--coverage`** measures coverage for this run. Without `withCoverage()` in the config it uses the defaults: measure `src/`, print a table. **`--no-coverage`** skips coverage that the config turns on, handy for a quick local run. See [code coverage](coverage.md).

**`--explorer`** runs the tests and then opens the Test Explorer with the results (in an interactive terminal). **`--no-explorer`** keeps it closed for this run even when the config has `withTestExplorer()`. The explorer is off by default, so scripts and agents need neither.

## init

```text
microunit init [--skill]
```

Writes `microunit.config.php` and `tests/example-tests.php` in the current directory, set up for Laravel, Symfony or CodeIgniter when `composer.json` uses one. It never overwrites existing files: if the config already exists, nothing changes and the exit code is `2`.

| Option | Effect |
| --- | --- |
| `--skill` | Also install the skill that teaches AI coding agents MicroUnit (`.claude/skills/microunit`) |

With `--skill` on a project that already has a config, only the skill is installed and the exit code is `0`. For agents other than Claude Code, `init --skill` prints the two lines to add to your `AGENTS.md`.

```bash
vendor/bin/microunit init
vendor/bin/microunit init --skill
```

See [getting started](getting-started.md).

## migrate

```text
microunit migrate [paths...] [--out=DIR]
```

Translates PHPUnit test classes (default: `tests/`) into MicroUnit test files in `tests-microunit/`. The originals stay untouched, so both suites can run side by side.

Properties become fixtures, helpers become functions, data providers become data sets, PHPUnit mocks become MicroUnit mocks. What needs manual work is written as a skipped test with the reason and the original code, and listed in `migration-report.json`. It also creates `microunit.config.php` from `phpunit.xml` when there is none.

| Option | Effect |
| --- | --- |
| `[paths...]` | PHPUnit test files or directories (default: `tests`) |
| `--out=DIR` | Where the MicroUnit tests go (default: `tests-microunit`) |

```bash
vendor/bin/microunit migrate
vendor/bin/microunit migrate tests/Unit --out=tests-new
```

Exits with `2` when it finds no PHP files to translate. The whole story is on [Migrating from PHPUnit](migrating-from-phpunit.md).

## integrity

```text
microunit integrity [revision]
```

Compares the tests with a git revision (default: `HEAD`) and lists removed tests, removed assertions, new skips, changed expected values, removed data sets and assertions that cannot fail. It is meant for reviewing changes, especially changes made by AI agents. Each finding is a reason to look, not proof of a mistake.

```bash
vendor/bin/microunit integrity
vendor/bin/microunit integrity origin/main
```

Exit code `0` when nothing was found, `1` when something was, and `2` when there is no git repository or the revision does not exist. To run it after every test run as well, configure [`withIntegrityCheck()`](configuration.md#ai-agents-and-strictness). Details on [AI coding agents](ai-agents.md).

## explorer

```text
microunit explorer
```

Opens the Test Explorer in the browser, where you run tests and read the results. It is opt-in: `withTestExplorer()` opens it after every interactive run, `--explorer` after a single one. Exits with `1` if the explorer server could not be started; the message points to its log file.

See [Test Explorer](test-explorer.md).

## help and version

| Command or option | Effect |
| --- | --- |
| `microunit help`, `--help`, `-h` | The overview of commands and options |
| `microunit help <command>` | Details and examples for one command, e.g. `microunit help migrate` |
| `microunit <command> --help` | The same as `help <command>` |
| `--version`, `-V` | The MicroUnit and PHP versions |

The help for the default command is `microunit help run`.

## Exit codes

| Code | Meaning |
| --- | --- |
| `0` | All tests passed |
| `1` | Tests failed, coverage below the minimum, or a configured strict check failed |
| `2` | Usage or configuration error |

"Strict check" means `failOnRiskyTests()` with a test that asserted nothing, or `withIntegrityCheck(..., failOnFindings: true)` with findings. Skipped tests never fail a run. Usage and configuration errors include an unknown option, a missing option value, an unknown test group, a path or config file that does not exist, and a config file that does not return a `MicroUnitConfig`.

The commands have their own codes, listed in their sections above.

## Environment variables

MicroUnit reads a few variables that CI systems and agents set anyway:

| Variable | Effect |
| --- | --- |
| `CI` | When set, the Test Explorer never opens and a missing snapshot fails the test instead of being written (see [snapshots](snapshots.md)) |
| `GITHUB_ACTIONS=true` | Adds GitHub Actions annotations (unless `withoutCiAnnotations()`) |
| `CLAUDECODE=1`, `MICROUNIT_AGENT=1` | Agent session: compact JSON output |
| `NO_COLOR`, `FORCE_COLOR` | Turn colour off or on (`--colors` and `--no-colors` set these for you) |

## Related

- [Configuration](configuration.md) for the settings these flags override.
- [Writing tests](writing-tests.md#test-ids) for how test ids are built.
- [CI/CD](ci-cd.md) for running MicroUnit in a pipeline.
