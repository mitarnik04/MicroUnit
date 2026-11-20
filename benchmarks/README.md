# MicroUnit benchmarks

This folder holds everything needed to check our speed claims: the harness, the pinned list of
projects, and every result we have published. If you think a number is wrong, run it yourself and
open an issue with your `results/` folder attached. That is the whole point of keeping it here.

## What is compared

PHPUnit, the standard, against MicroUnit, on the test suites of popular open-source PHP projects.
For parallel runs, PHPUnit is run through ParaTest, the usual way to run PHPUnit in parallel, with
exactly the same PHPUnit version. No synthetic "10,000 empty tests" suite: we wanted the tests
people actually write, with data providers, mocks, setUp methods, file fixtures and all.

Pest is not in the comparison. It runs on top of PHPUnit, so on the same tests it can only match
PHPUnit or take longer; measuring it would add time to every benchmark run without telling you
anything the PHPUnit numbers don't.

The projects were picked to cover different shapes of test suites, not to flatter MicroUnit:
nesbot/carbon is in it although it is the suite where MicroUnit's lead is smallest (see the limits
below).

| Project | Why it is in the set |
|---|---|
| brick/math | ~25,000 data-driven tests: the runner's own overhead per test dominates |
| nesbot/carbon | 1,000+ test classes and deep inheritance: a big, real application-style suite |
| webmozart/assert | ~5,000 data sets on one test method |
| league/commonmark | file fixtures and spec tests, many small classes |
| symfony/yaml | a Symfony component suite on PHPUnit 13 |
| monolog/monolog | mocks, handlers, I/O |

The exact repository, tag and commit of each project, the commands per tool and every setup step
are in [`corpus.json`](corpus.json). The harness refuses to run a project that is not checked out at
the pinned commit.

## The same tests for every tool

MicroUnit cannot run PHPUnit test classes, so the MicroUnit side is generated with
`microunit migrate` from the project's own tests. Nobody touches the result by hand; the migration
runs again at the start of every benchmark.

A test method is excluded from **every** tool when:

- `microunit migrate` cannot translate it automatically (process isolation, `#[Depends]`, ...),
- it fails under PHPUnit on the benchmark machine (network, time zone, operating system), or
- its migrated version fails (a limitation of `migrate`), or
- it fails when ParaTest runs the suite in parallel (it depends on the order of the tests), or
- PHPUnit and its migrated version report a different number of tests for it. That happens when a
  requirement is not met (a missing extension): PHPUnit skips the method as one test without running
  its data provider, the migrated test skips every data set.

A few tests are excluded by hand in `corpus.json`, each with its reason (one hangs on Windows, one
waits 20 seconds for a server, one depends on the wall clock).

Every exclusion is written to `results/<date>/excluded/<project>.json` with its reason. Before a
scenario is measured, every tool runs once and must report the same number of tests. If the counts
differ, the scenario is marked as not comparable and no time is published for it.

## How it is measured

- Wall-clock time of the whole process, from start to exit: what you wait for. Not the framework's
  own "time" line.
- Warm-up runs first (discarded), then rounds in which every tool runs once. The order of the tools
  rotates from round to round, so none of them always benefits from a warm file cache or suffers
  from a hot CPU.
- Peak memory through the same tiny `auto_prepend_file` probe for every tool (for parallel runs:
  the largest process). It reads the operating system's peak resident set (`getrusage()`), which
  includes the PHP binary itself. PHP's own `memory_get_peak_usage()` would be wrong here: PHPUnit
  10+ resets it before every test, so it would only show PHPUnit's last test.
- Same PHP binary, same `php.ini`, the same `-d memory_limit=-1` for every tool, OPcache settings as
  in `php.ini` (recorded). Parallel runs use the same number of workers for every tool.
- The same environment variables for every tool, minus the ones AI coding agents and CI systems
  set (`CLAUDECODE`, `AI_AGENT`, `CI`, ...): PHPUnit and PHPStan change their output when they see
  them, so every tool runs as it would in a plain terminal.
- MicroUnit runs with its defaults: no Test Explorer (it is opt-in), no coverage.
- Results: median, p90, minimum, maximum and coefficient of variation per tool, and the raw time of
  every single run in `runs.jsonl`.

Scenarios:

| Scenario | What it shows |
|---|---|
| Full suite, sequential | the classic CI run |
| Full suite, parallel | PHPUnit through ParaTest against MicroUnit's built-in parallel runner |
| One test file | the edit-and-rerun loop, what developers and AI agents do dozens of times an hour |
| Startup | the fixed cost of every single run |

## What is recorded with every result

`environment.json`: CPU model, cores and threads, RAM, operating system and build, power plan and
whether the machine ran on battery, antivirus real-time scanning, CPU load at the start, the PHP
version and build, a hash of the loaded `php.ini`, every loaded extension, OPcache/JIT/Xdebug/PCOV
settings, MicroUnit's version and commit, and a hash of its source code. `summary.md` lists each
tool's exact version line. `outputs/` keeps the console output of every tool's verification run.
No host names, user names or local paths are recorded.

## Reproducing

1. Check out each project at its pinned commit, as the `setup` steps in `corpus.json` describe,
   under one work directory (`<work>/<slug>`). A copy with ParaTest goes next to it
   (`projects-paratest/<slug>`, see `paratestSetup`): ParaTest installed, PHPUnit pinned to the very
   version of the checkout.
2. Run, with the PHP binary you want to measure:

   ```bash
   php benchmarks/run.php --work=/path/to/projects --runs=5 --warmup=1 --workers=4
   ```

   `--projects=math,yaml` and `--scenarios=suite,file` narrow it down.
3. Read `benchmarks/results/<date>/summary.md`.

Close other programs first. A benchmark on a busy machine measures the machine.

## Known limits

We would rather list them than have someone else discover them:

- The published results come from one machine (see `environment.json`). Numbers on yours will
  differ; the ratios are what to compare.
- Only suites on a current PHPUnit (10 or newer) are in the set. Suites still on PHPUnit 9 could
  not be compared in parallel: ParaTest 6, the last for PHPUnit 9, filters tests only in a mode that
  distributes work per method instead of per class.
- Tests that need process isolation are excluded everywhere, which favours nobody but makes the
  slowest PHPUnit tests disappear from the comparison.
- MicroUnit runs migrated tests, not hand-written ones. `microunit migrate` copies inherited test
  methods into every subclass, because a MicroUnit test file has no base class. Where hundreds of
  test classes share one parent, that multiplies the code MicroUnit has to load: nesbot/carbon's
  ~800 localization tests turn 14 MB of PHPUnit tests into 40 MB of MicroUnit tests, which costs
  MicroUnit memory and time there. Hand-written MicroUnit tests would share that code once.
- MicroUnit's parallel workers keep compiled code in an OPcache file cache in `.microunit/` (a
  built-in feature, on by default). ParaTest workers run with the OPcache settings of `php.ini`,
  which in the published runs means without OPcache. Sequential runs use the same
  `php.ini` settings for every tool.
- Code coverage is not part of this benchmark yet: PHPUnit needs PCOV or Xdebug, MicroUnit needs
  nothing, and a fair setup for that comparison is still to be done.

## Results

| Date | Machine | Summary |
|---|---|---|
| 2026-11-19 | Intel Core i7-1355U, Windows 11, PHP 8.6.0 | [summary](results/2026-11-19/summary.md): MicroUnit faster in 24 of 24 comparisons; median across projects 2.05× (full suite), 2.91× (4 workers, against ParaTest), 2.62× (one test file), 2.93× (startup) |

The charts and tables on the [benchmarks page](https://mitarnik04.github.io/MicroUnit/benchmarks/) and in `assets/brand/benchmarks/` are rendered from `summary.json` and `environment.json` of a run (`php assets/brand/build.php benchmarks`, with the run date set in `assets/brand/src/benchmark.html`). The numbers live only in the results, never in the images.
