---
layout: default
title: Parallel runs
description: Run your suites across several PHP processes with --parallel or runInParallel(), and keep shared resources safe with TEST_TOKEN.
---

# Parallel runs

MicroUnit can spread a run over several PHP worker processes and still report the results exactly as a sequential run would, in the same order. It needs no extension and no extra package: the workers are MicroUnit itself, started with `proc_open()`. This page explains how the work is split, what that means for tests that share a database or a directory, and when going parallel is not worth it.

## Turning it on

For one run:

```bash
vendor/bin/microunit --parallel        # one worker per CPU core
vendor/bin/microunit --parallel=4      # exactly four workers
vendor/bin/microunit --no-parallel     # sequential, whatever the config says
```

For every run, in `microunit.config.php`:

```php
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->runInParallel()      // one worker per CPU core; runInParallel(4) for four
    ->build();
```

`--parallel=1` and `--no-parallel` both mean sequential. A few things quietly fall back to a sequential run:

- fewer than two suites are selected (a suite is never split, see below),
- with one worker per core (`--parallel` or `runInParallel()` without a number), the run is estimated to take under a second: the previous run's time per test, times the number of tests selected now. Starting the workers would cost more than it saves. An explicit number of workers always runs in parallel,
- `proc_open()` is disabled in your PHP configuration (MicroUnit prints `proc_open() is disabled on this system; running tests sequentially.`).

MicroUnit never starts more workers than there are selected suites.

## How the work is split

The unit of distribution is the **suite**, never the single test. Every test of a suite runs in the same worker, one after another, in definition order. That is deliberate: a suite's `setUpOnce()`, its shared fixtures (`shared: true`) and anything else its tests share (an open connection, a temp directory, a server started for the suite) stay in one process and are created exactly once, just like in a sequential run.

Suites are dealt out largest first, each to the worker with the fewest tests so far. Every worker computes the same split independently, so no coordination is needed: worker 2 of 4 knows which suites are its own.

Two consequences worth knowing:

- **Balance is by test count, not by duration.** A suite with ten slow integration tests weighs the same as a suite with ten one-millisecond unit tests. Usually that evens out across a real project; if one slow suite dominates, splitting it into two suites (two files) is the fix.
- **One huge suite can't be split.** If 90% of your tests live in one suite, one worker gets 90% of the work.

Each worker is a fresh PHP process. It loads your config, the bootstrap file, the framework integration and all test files, then runs only its share.

After all workers have finished, the main process collects their results, prints them suite by suite in definition order and writes the summary. You see the output at the end rather than suite by suite as it happens.

`--stop-on-failure` works per worker: a worker stops at its first failure, the others finish their share.

## Shared resources and worker tokens

Tests that are perfectly isolated in memory can still collide outside it: two workers writing the same SQLite file, truncating the same MySQL database, or binding the same port. MicroUnit gives every worker two environment variables to tell them apart:

| Variable | Values | Meant for |
| --- | --- | --- |
| `MICROUNIT_WORKER` | `0`, `1`, `2`, ... | checking that you run inside a MicroUnit worker, and which one |
| `TEST_TOKEN` | `1`, `2`, `3`, ... | the cross-framework convention for "which parallel process am I" |

In a sequential run neither variable is set.

`TEST_TOKEN` is the variable Laravel's `ParallelTesting::token()` reads, so code in your app (or in Laravel) that keys off that token sees a distinct value in every worker. For your own resources, use it directly:

```php
// tests/bootstrap.php
$token = getenv('TEST_TOKEN') ?: 'main';

// One SQLite file and one scratch directory per worker.
putenv("DB_DATABASE=" . __DIR__ . "/../var/test-$token.sqlite");
define('TEST_SCRATCH_DIR', sys_get_temp_dir() . "/myapp-tests-$token");
```

The bootstrap runs once per worker, so this is also the right place to create a per-worker database schema.

## Coverage in parallel runs

[Code coverage](coverage.md) works with `--parallel` with no extra setting. Each worker measures the code its tests run, sends its line counts back with its results, and the main process merges them before writing the reports. The reports and the minimum check cover the whole run, exactly as they would sequentially.

Workers that do not measure coverage get one extra speed-up: when the OPcache extension is available, they run with its file cache enabled (stored in `.microunit/cache/opcache`), so compiled code is reused from one run to the next. Coverage runs skip this, because OPcache could bypass the instrumentation.

## When parallel does not pay off

Every worker is a new PHP process that boots MicroUnit, your autoloader, your bootstrap and possibly a whole framework, and loads every test file. That startup cost is paid once per worker. Parallel runs win when the tests themselves take noticeably longer than that, and lose when they don't.

- **Tiny or fast suites.** If your whole suite finishes in a few hundred milliseconds sequentially, the extra processes often cost more than they save. Measure both: `time vendor/bin/microunit --no-parallel` against `time vendor/bin/microunit --parallel`.
- **A heavy bootstrap.** Booting a large framework per worker adds up. More workers than cores rarely help.
- **One dominant suite**, as described above.
- **Tight edit-test loops.** When you run one file or `--failed` while working on a bug, a single process is usually fastest. Parallel shines for the full suite before a commit and in CI.

A common setup: no `runInParallel()` in the config, and `--parallel` in CI and for the full local run.

## When a worker crashes

A test that calls `exit()`, hits a fatal error or crashes PHP takes its worker down with it. MicroUnit notices the missing results and fails every test that worker was given, with the exit code and the tail of the worker's output:

```text
-- Crash --
[✖] exits_the_process (0.00ms)
    RuntimeException: The parallel worker running this test stopped unexpectedly (exit code 3).
    at /path/to/vendor/microunit/microunit/src/Parallel/ParallelRunner.php:160
```

(The `at` line points into MicroUnit because that is where the failure is recorded; the test file is the one to look at.)

The run as a whole still completes and the other workers' results are reported normally.

Because every test of that worker fails, the output can list several suites, and the culprit is one test among them. Run them without parallel to see the real error: one suite at a time with `--filter` (`vendor/bin/microunit --no-parallel --filter=Crash`), or all at once with `--no-parallel`.

Two related details:

- Whatever the workers print outside of tests goes to a log file in `.microunit/tmp` that is deleted after the run. Its last 2000 characters are included in the crash message.
- Workers start with the same `php.ini` and the same memory limit as the main process, so `php -d memory_limit=1G vendor/bin/microunit --parallel` applies to every worker. Other settings passed with `-d` do not reach them; put those in `php.ini` or set them with `ini_set()` in your bootstrap file.

## Related pages

- [Coverage](coverage.md) for measuring coverage, also in parallel.
- [Configuration](configuration.md) for `runInParallel()` and the other options.
- [CI/CD](ci-cd.md) for running the suite in parallel on a build server.
- [Benchmarks](benchmarks.md) for how MicroUnit's speed was measured.
- [Troubleshooting](troubleshooting.md#a-parallel-worker-stopped-unexpectedly) when workers keep crashing.
