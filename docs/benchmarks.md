---
layout: default
title: Benchmarks
description: MicroUnit against PHPUnit and ParaTest on the real test suites of six popular PHP projects. Method, raw data and every exclusion are published.
---

# Benchmarks

MicroUnit was measured against PHPUnit on the real test suites of six popular PHP projects: the same tests, on the same machine, with the same PHP. Parallel runs were compared with ParaTest. The method, the raw data of every run and every excluded test are in [`benchmarks/`](https://github.com/mitarnik04/MicroUnit/tree/main/benchmarks), so anyone can check the numbers or reproduce them.

## At a glance

- **MicroUnit was faster in all 24 comparisons:** four scenarios on six projects.
- **The typical gain:** across the six projects, the median is 2.05× for a full suite, 2.91× for a parallel run against ParaTest, 2.62× for re-running one test file and 2.93× for startup.
- **Less memory on five of six projects.** The exception, nesbot/carbon, is explained [below](#peak-memory).

The run was on 19 November 2026, with MicroUnit 1.0.0 against PHPUnit 10.5 to 13.4 (each project on its own version) and ParaTest 7.4 to 7.29. All times are medians of 5 measured runs after a warm-up run, on PHP 8.6.0 with OPcache off, on an Intel Core i7-1355U laptop with Windows 11. The ratios are what to compare; absolute times on your machine will differ.

## Full test suite

All tests in one process, the way CI runs them.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="{{ '/assets/img/benchmarks/suite-dark.png' | relative_url }}">
  <img alt="Full test suite: MicroUnit is 1.03 to 5.14 times faster than PHPUnit" src="{{ '/assets/img/benchmarks/suite-light.png' | relative_url }}" width="100%">
</picture>

| Project | Tests | PHPUnit | MicroUnit | MicroUnit is |
|---|---:|---:|---:|---:|
| brick/math | 24,782 | 3.74 s | 0.77 s | 4.87× faster |
| nesbot/carbon | 6,233 | 40.4 s | 35.9 s | 1.13× faster |
| webmozart/assert | 4,942 | 0.80 s | 0.16 s | 5.14× faster |
| league/commonmark | 4,340 | 1.92 s | 0.82 s | 2.36× faster |
| symfony/yaml | 1,427 | 1.64 s | 0.94 s | 1.74× faster |
| monolog/monolog | 1,197 | 9.31 s | 9.00 s | 1.03× faster |

The gap is widest where the runner's own cost per test decides the time: brick/math runs almost 25,000 small data-driven tests, webmozart/assert about 5,000 data sets of one test method.

### Why carbon and monolog are close

Both suites spend most of their time in the tests themselves, and no runner can make that faster.

- **monolog/monolog** waits. About 8 of its 9 seconds are tests that wait on purpose: a test that tries to reach a CouchDB server on `localhost:5984` and skips itself after a 4-second connection attempt, a stream timeout of 2.7 seconds, socket timeouts of 1.3 seconds. Both tools wait exactly as long.
- **nesbot/carbon** works. Its `setUp()` and `tearDown()` reset time zones, locales and the test clock before and after each of its 6,233 tests, and many tests format dates in hundreds of languages. Compared test class by test class, MicroUnit is as fast or faster everywhere; the runner is just a small share of the total.

Both suites are measured as they ship. Leaving out slow tests would have made the ratios look better and the comparison less honest.

## Parallel runs

MicroUnit's built-in parallel runner against ParaTest, both with 4 worker processes.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="{{ '/assets/img/benchmarks/parallel-dark.png' | relative_url }}">
  <img alt="Full test suite with 4 workers: MicroUnit is 1.13 to 4.71 times faster than ParaTest" src="{{ '/assets/img/benchmarks/parallel-light.png' | relative_url }}" width="100%">
</picture>

| Project | ParaTest | MicroUnit | MicroUnit is |
|---|---:|---:|---:|
| brick/math | 3.57 s | 0.86 s | 4.14× faster |
| nesbot/carbon | 29.9 s | 17.1 s | 1.75× faster |
| webmozart/assert | 1.32 s | 0.28 s | 4.71× faster |
| league/commonmark | 3.70 s | 0.91 s | 4.06× faster |
| symfony/yaml | 1.50 s | 1.04 s | 1.44× faster |
| monolog/monolog | 5.22 s | 4.63 s | 1.13× faster |

Running in parallel pays off for long suites: carbon goes from 35.9 to 17.1 seconds, monolog from 9.0 to 4.6. For suites that finish in about a second, starting the workers costs more than it saves, with MicroUnit and with ParaTest alike.

The benchmark forces 4 workers on both tools to compare like with like. In daily use, `--parallel` without a number checks the previous run's time per test first and stays sequential when parallel would not pay off; see [parallel runs](parallel.md).

## One test file

The edit-and-rerun loop: change something, run the test file, repeat. Developers and AI agents do this dozens of times an hour, so this is the number you feel most.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="{{ '/assets/img/benchmarks/file-dark.png' | relative_url }}">
  <img alt="One test file: MicroUnit is 1.38 to 4.92 times faster than PHPUnit" src="{{ '/assets/img/benchmarks/file-light.png' | relative_url }}" width="100%">
</picture>

| Project | Tests | PHPUnit | MicroUnit | MicroUnit is |
|---|---:|---:|---:|---:|
| brick/math | 2,694 | 0.57 s | 0.12 s | 4.59× faster |
| nesbot/carbon | 70 | 0.24 s | 0.17 s | 1.38× faster |
| webmozart/assert | 4,328 | 0.69 s | 0.14 s | 4.92× faster |
| league/commonmark | 11 | 0.41 s | 0.13 s | 3.27× faster |
| symfony/yaml | 423 | 0.78 s | 0.39 s | 1.97× faster |
| monolog/monolog | 20 | 0.16 s | 0.09 s | 1.69× faster |

## Startup

The fixed cost of every run: start the runner, load the suite, select no test.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="{{ '/assets/img/benchmarks/startup-dark.png' | relative_url }}">
  <img alt="Startup: MicroUnit is 1.16 to 5.85 times faster than PHPUnit" src="{{ '/assets/img/benchmarks/startup-light.png' | relative_url }}" width="100%">
</picture>

| Project | PHPUnit | MicroUnit | MicroUnit is |
|---|---:|---:|---:|
| brick/math | 0.90 s | 0.15 s | 5.85× faster |
| nesbot/carbon | 1.04 s | 0.90 s | 1.16× faster |
| webmozart/assert | 0.31 s | 0.09 s | 3.38× faster |
| league/commonmark | 0.74 s | 0.24 s | 3.12× faster |
| symfony/yaml | 0.60 s | 0.22 s | 2.74× faster |
| monolog/monolog | 0.26 s | 0.13 s | 2.08× faster |

Carbon's startup is close because loading its 1,000+ test files dominates for both tools.

## Peak memory

The operating system's peak for the process during a full sequential run.

| Project | PHPUnit | MicroUnit | MicroUnit uses |
|---|---:|---:|---:|
| brick/math | 121 MiB | 67 MiB | 45% less |
| nesbot/carbon | 165 MiB | 226 MiB | 37% more |
| webmozart/assert | 51 MiB | 35 MiB | 31% less |
| league/commonmark | 93 MiB | 50 MiB | 46% less |
| symfony/yaml | 68 MiB | 46 MiB | 32% less |
| monolog/monolog | 366 MiB | 355 MiB | 3% less |

Carbon is the one project where MicroUnit needs more memory, and the reason is the migration, not the runner. A MicroUnit test file has no base class, so `microunit migrate` copies inherited test methods into every subclass. Carbon's ~800 localization test classes share one parent, which turns 14 MB of PHPUnit tests into 40 MB of MicroUnit tests. Hand-written MicroUnit tests would share that code once.

Monolog's peak, in both tools, comes from one test that raises the memory limit to 3 GB on purpose.

Memory is measured as the operating system's peak resident set, not with `memory_get_peak_usage()`: PHPUnit resets PHP's own counter before every test, so it would only show PHPUnit's last test.

## How it was measured

- **The same tests on both sides.** Each project's PHPUnit suite was converted with `microunit migrate`. A test is left out of every tool's run when it fails under PHPUnit on this machine, could not be migrated automatically, fails after migration, or depends on the test order under ParaTest. Every exclusion is listed with its reason in `results/<date>/excluded/`, and each scenario only counts if both tools ran exactly the same number of tests.
- **Fair conditions.** The same PHP binary and `php.ini` for every tool, the same `-d memory_limit=-1`, an environment without CI or agent variables, antivirus scanning off, the laptop on AC power. The order of the tools rotates from round to round, so no tool always gets the warm file cache.
- **Timing.** Wall-clock time from process start to exit, 1 warm-up run and 5 measured runs per tool. The summary reports median, p90, minimum, maximum and coefficient of variation; the raw time of every run is in `runs.jsonl`.

| Project | Excluded test methods | Main reason |
|---|---:|---|
| brick/math | 8 | run in a separate process (not supported by `migrate`) |
| nesbot/carbon | 110 | fail after migration, or not migrated automatically |
| webmozart/assert | 42 | check PHPUnit's own `TestCase` class |
| league/commonmark | 106 | fail after migration |
| symfony/yaml | 0 | |
| monolog/monolog | 107 | not migrated automatically, or fail after migration |

The [benchmark README](https://github.com/mitarnik04/MicroUnit/tree/main/benchmarks#readme) has the complete method and the known limits, for example that code coverage is not compared yet.

## Reproducing it

The harness is part of the repository. It needs PHP, Composer and Git, takes about half an hour, and writes everything to `benchmarks/results/<date>/`:

```bash
php benchmarks/run.php --work=/path/to/projects --runs=5 --warmup=1 --workers=4
```

The [benchmark README](https://github.com/mitarnik04/MicroUnit/tree/main/benchmarks#readme) explains how to check out the six projects. Close other programs first: a benchmark on a busy machine measures the machine. Different results on your hardware are welcome as an issue, with the `results/` folder attached.

## Related pages

- [Parallel runs](parallel.md) for how MicroUnit splits a run and when it stays sequential.
- [Migrating from PHPUnit](migrating-from-phpunit.md) for the converter the benchmark uses.
- [AI agents](ai-agents.md) for why the cost of a single run matters when an agent runs tests fifty times an hour.
