---
layout: default
title: Troubleshooting
description: Fixes for the problems people actually run into, from missing tests and config files to explorer ports, parallel crashes and unexpected JSON output.
---

# Troubleshooting

Most problems with a test runner come down to a handful of causes: it looks for files in a different place than you think, it loads a different config than you think, or something in the environment changes its behaviour. This page goes through the common symptoms, what causes them and how to fix them. MicroUnit tries hard to say what went wrong in its error messages, so the quoted messages below are the real ones, which makes this page easy to search.

## No tests found

```text
No test files found. Test files must match: *-tests.php
```

MicroUnit only loads files whose **name** matches one of the configured patterns. The default pattern is `*-tests.php`, so a PHPUnit-style `CartTest.php` is ignored until you say otherwise:

```php
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withTestFilePatterns('*Test.php')
    ->build();
```

Things worth knowing about patterns:

- They match the file name, not the path, and they are case-insensitive. `*`, `?` and character classes like `Test[A-Z]*.php` work.
- `withTestFilePatterns()` sets the list, `addTestFilePattern()` adds one more. As soon as you configure any pattern, the default `*-tests.php` no longer applies, so list it again if you still want it.
- Directories named `vendor`, `node_modules`, `.git` and `.microunit` are never searched.
- A file you name on the command line (`vendor/bin/microunit tests/cart.php`) runs even if it doesn't match a pattern. A directory on the command line is searched with the patterns.

Without `withTestDir()`, MicroUnit searches `tests/` next to the config file; `microunit init` creates it with a first test. A directory that doesn't exist is an error:

```text
The test directory '/app/spec' does not exist. Check withTestDir()/withTestGroup() in your config.
```

Note that a run that finds no test files still exits with `0`. If CI suddenly reports "0/0 passed", the patterns or the directory are the first thing to check.

If the files are found but a suite is empty, check that the file actually registers tests: `TestSetup::getTester('Suite')` and at least one `$tester->define(...)`. `vendor/bin/microunit --list` shows what MicroUnit sees without running anything.

## Your configuration is not applied

MicroUnit looks for a file named exactly `microunit.config.php`, starting in the current working directory and walking up through its parents. The first one it finds wins. If it finds none, it runs with the defaults, without a warning.

So if your settings seem to be ignored, one of these is usually true:

- **The file has a different name.** `microunit.php` or `microunit.config.dist.php` are not picked up.
- **It is not on the search path.** A config in `tools/` is never found when you run from the project root, because the search only goes up, never sideways or down.

```text
/my-project
├── tools/
│   └── microunit.config.php   # not found: not on the way up from /my-project
├── tests/
└── vendor/
```

- **Another config is closer.** A stray `microunit.config.php` in a subdirectory wins when you run from inside it.

To take the guessing out of it, name the file explicitly:

```bash
vendor/bin/microunit --config=tools/microunit.config.php
```

```text
The config file 'tools/microunit.config.php' does not exist.
```

Paths inside the config (`withTestDir()`, `withBootstrapFile()`, coverage paths, the runtime directory) are relative to the config file, not to the directory you run from. Absolute paths work too, so `__DIR__ . '/tests'` is fine.

### The config file must return the config

```text
/app/microunit.config.php must return a MicroUnit\Config\MicroUnitConfig, but returned int. Did you forget 'return' or '->build()'?
```

The file has to `return` the result of `build()`:

```php
<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

return MicroUnitConfigBuilder::create()   // "return" is easy to forget
    ->withTestDir('tests')
    ->build();                             // and so is build()
```

"returned int" is what you get without `return` (PHP's `require` returns `1`), "returned MicroUnit\Config\MicroUnitConfigBuilder" means `->build()` is missing. MicroUnit exits with code `2` for configuration errors, so CI fails clearly instead of running nothing.

## No output at all

If a run prints nothing, check whether your config calls `withTestWriters()`. It replaces the default console writer, so this config produces no console output:

```php
->withTestWriters(new JUnitTestWriter('build/junit.xml'))   // the console writer is gone
```

Use `addTestWriter()` to keep the console output, or list the console writer explicitly:

```php
->withTestWriters(new MinimalStringTestWriter(), new JUnitTestWriter('build/junit.xml'))
```

The [output](output.md) page lists every writer.

## Classes are not found, or the bootstrap fails

MicroUnit loads your Composer autoloader for you: it looks for `vendor/autoload.php` from the working directory upwards (falling back to the one MicroUnit itself was installed with). You don't need a bootstrap file just to autoload.

When a class is not found:

- **Test helpers and fixtures classes** usually live in an `autoload-dev` namespace (`"Tests\\": "tests/"`). Check that the mapping exists and run `composer dump-autoload` after adding it.
- **Classes declared in test files** are only available once that file is loaded. Put shared helpers in their own autoloaded files instead.
- **A missing `use` statement** for `Assert` or `TestSetup` shows up the same way. Every test file needs `use MicroUnit\Assertion\Assert;` and `use MicroUnit\Setup\TestSetup;` (or whatever it uses).

A bootstrap file configured with `withBootstrapFile()` must exist, relative to the config file:

```text
The bootstrap file '/app/tests/bootstrap.php' does not exist.
```

Don't `require` your own source files from `microunit.config.php` itself. It works for running tests, but those files are then loaded before coverage starts and can't be measured. The bootstrap file is the right place.

### Warnings and fatal errors

MicroUnit keeps PHP's warnings, notices and deprecations out of the test output and writes them to a run log in `.microunit/logs/`. Only the latest run's log is kept, unless you configure `persistRunLogs()`. A fatal error stops the run with a pointer to that log:

```text
MicroUnit stopped on a fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)
  in /app/src/Importer.php:88
  Full log: /app/.microunit/logs/2026-11-19T00-50-39Z-19136.log
```

If something behaves oddly and the test output gives no reason, look in that log first.

## Coverage is zero or suspiciously low

Go through these in order:

1. **OPcache.** If the run prints `Coverage: OPcache is enabled for the CLI and may bypass instrumentation`, OPcache is serving cached code instead of the instrumented version. Run with `php -d opcache.enable_cli=0 vendor/bin/microunit --coverage`, or set `opcache.enable_cli=0` in the `php.ini` your CI uses.
2. **Files loaded too early.** `Coverage: N file(s) were loaded before coverage started and cannot be measured` means something included source files before the tests started, typically `microunit.config.php`. Move those includes into the bootstrap file.
3. **The include path.** `withCoverage()` measures `src/` by default, relative to the config file. If your code lives in `app/` or `lib/`, say so: `->withCoverage(include: 'app')`.
4. **Files that could not be parsed.** `Coverage: /app/src/Weird.php is not measured (...)` means the instrumenter didn't understand some syntax in that file and left it out. The file still runs; it just isn't counted.
5. **No tokenizer.** Coverage needs PHP's tokenizer extension, which some minimal Docker images leave out.

Files that no test loads are reported with 0%, on purpose: untested code should lower the number. The [coverage](coverage.md) page explains how the measurement works.

## The Test Explorer does not open

It is opt-in: a run opens it only with `--explorer` or `withTestExplorer()` in the config. Even then it stays closed when:

- the `CI` environment variable is set (even locally, if your shell profile sets it),
- the output is piped or redirected, or the tests run inside a tool that captures output (including AI agents),
- the run used `--no-explorer`.

`vendor/bin/microunit explorer` opens it on demand in all of these cases.

### The port is busy

```text
The Test Explorer could not be started on port 7357. See /app/.microunit/explorer/server.log
```

Something else is listening on port 7357, often the explorer of another project. The server log says so (`Test Explorer: port 7357 is not available (...)`). Pick another port for this project:

```php
->withTestExplorer(port: 7358)
```

### The browser doesn't open

MicroUnit opens the URL with `start` on Windows, `open` on macOS and `xdg-open` on Linux. If that fails (a server without a desktop, WSL without a browser bridge, a missing `xdg-open`), the URL is still printed: open `http://127.0.0.1:7357/` yourself.

### "Explorer stopped"

The explorer server shuts down after 30 minutes without requests. An open tab then shows "Explorer stopped · run microunit to restart". Run the tests or `vendor/bin/microunit explorer` and the same tab reconnects. See the [Test Explorer](test-explorer.md) page for the details.

## A parallel worker stopped unexpectedly

```text
RuntimeException: The parallel worker running this test stopped unexpectedly (exit code 255).
```

A test in that worker took the process down: a fatal error, an `exit()` or `die()` in the code under test, a segfault in an extension, or running out of memory. Every test the worker was given is reported with this message, so the named suite is a starting point, not the culprit itself. When the worker printed something before it died, the last part of it is included under "Worker output".

To find the real error, run the suite sequentially, where the fatal error is reported normally:

```bash
vendor/bin/microunit --no-parallel --filter=ImporterSuite
```

If tests pass sequentially and fail in parallel without crashing, they probably share something outside of PHP's memory: a database, a file, a port. Give each worker its own copy using the `TEST_TOKEN` environment variable, as described in [parallel runs](parallel.md#shared-resources-and-worker-tokens).

Workers start with the same `php.ini` and memory limit as the main process, but other settings passed with `-d` on the command line do not reach them. If a `-d` setting fixes a sequential run but not a parallel one, move it into `php.ini` or into `ini_set()` in the bootstrap file.

## Framework integration: "Is the framework (and phpunit/phpunit) installed?"

```text
The test case class Illuminate\Foundation\Testing\TestCase does not exist. Is the framework (and phpunit/phpunit) installed?
```

The Laravel, Symfony and CodeIgniter integrations run your tests inside the framework's own test case class, so the framework's helpers (`actingAs()`, `assertDatabaseHas()`, the HTTP client) behave exactly as they do under PHPUnit. Those test case classes extend PHPUnit's `TestCase`. MicroUnit itself has no dependencies, but with a framework integration your project needs `phpunit/phpunit` in `require-dev`. A new Laravel project already has it; keep it there after migrating from PHPUnit. The [frameworks](frameworks.md) page has the details.

## JSON output appears when you did not ask for it

If your terminal suddenly shows one long line of JSON instead of the usual output, MicroUnit thinks an AI agent is running the tests. It decides that from two environment variables:

- `CLAUDECODE=1`, which Claude Code sets for the commands it runs. A terminal opened from within a Claude Code session, or a shell that inherited its environment, has it too.
- `MICROUNIT_AGENT=1`, which you or a tool may have exported in a shell profile.

Check with `echo $CLAUDECODE $MICROUNIT_AGENT` (or `echo $env:CLAUDECODE` in PowerShell). For a single run, pass `--no-agent`. To turn agent output off for the project regardless of the environment:

```php
->withAgentOutput(false)
```

The reverse also happens: an agent sees no JSON because the config has `withAgentOutput(false)`. The [AI agents](ai-agents.md) page explains the switches.

## `--failed` runs everything

When `--failed` finds nothing to re-run, it prints "No failed tests in the previous run" and runs the full selection instead.

`--failed` reads the results the previous run saved in `.microunit/explorer/`. If that run had no failures, if `.microunit` was deleted, or if the previous run happened with a different config (and therefore a different runtime directory), there is nothing to re-run.

## A data set or fixture gets the wrong arguments

```text
ArgumentCountError: Too few arguments to function MicroUnit\Cli\Application::{closure:/app/tests/math-tests.php:8}(), 2 passed in ... and exactly 3 expected
```

Parameters of a test closure are filled in this order: parameters named like a fixture get that fixture, everything else is filled from the positional values. The positional values are, in order, the value returned by `setUp()` (or by `setUpOnce()` when there is no `setUp()`) if it returned anything, then the values of the data set. The error above usually means one of two things:

- a data set has fewer values than the closure has non-fixture parameters, or
- `setUp()` returns a value, which then takes the first positional slot and shifts the data set values by one.

Give every data set the same number of values as the closure expects, and either accept the `setUp()` value as the first non-fixture parameter or stop returning it (fixtures are usually the better tool). [Writing tests](writing-tests.md) covers fixtures and data sets in detail.

## Windows notes

MicroUnit's own test suite runs on Windows, Linux and macOS in CI, so most things just work. A few details:

- **Running it.** `vendor/bin/microunit` works in PowerShell and Git Bash; in CMD, Composer's `vendor\bin\microunit.bat` proxy does the same.
- **Regular expressions in Git Bash.** Git Bash turns arguments that start with `/` into Windows paths, so `--filter=/^Cart::/` selects nothing. Prefix the command with `MSYS_NO_PATHCONV=1`, or use a plain-text filter (`--filter=Cart::`).
- **Colours.** MicroUnit enables ANSI colours in Windows Terminal and modern consoles. If you see escape codes like `[32m` instead of colours in an old console, pass `--no-colors`.
- **The explorer server** runs in its own process group, so pressing Ctrl+C in the terminal does not take it down with the test run.
- **Paths.** Coverage include and exclude paths are compared case-insensitively on Windows, matching the file system. Paths in the agent JSON use forward slashes.
- **Parallel runs** use the `NUMBER_OF_PROCESSORS` environment variable to decide how many workers "one per core" means.
- **`-d` settings and parallel workers.** As on every OS, workers read `php.ini` and inherit the memory limit, but not other `-d` flags. On Windows installs without a `php.ini`, workers start with no ini file at all, so extensions you enable with `-d extension=...` are missing there. Create a `php.ini` instead.

## Still stuck?

Run with `--no-parallel --no-coverage --no-agent` to rule out the moving parts, check the run log in `.microunit/logs/`, and if it still looks wrong, open an issue with the command, the output and your `microunit.config.php`. The [CLI](cli.md) and [configuration](configuration.md) pages document every flag and option.
