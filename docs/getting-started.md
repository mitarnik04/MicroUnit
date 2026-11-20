---
layout: default
title: Getting started
description: Install MicroUnit, generate a config and a first test, run it, and read the output.
---

# Getting started

This page takes you from an empty project to a passing test run in about two minutes. You install one Composer package (it has no dependencies of its own), let `microunit init` write the config and a first test, run it, and learn to read what comes back. MicroUnit needs PHP 8.5 or newer and is tested on PHP 8.5 and 8.6.

## Install

```bash
composer require --dev microunit/microunit
```

That is the whole dependency tree. No PHPUnit underneath, no Symfony components, nothing pulled in behind your back. The package ships one binary, `vendor/bin/microunit`. On Windows, Composer also creates `vendor\bin\microunit.bat` for CMD; PowerShell and Git Bash are happy with the forward-slash version.

## Generate a config and a first test

```bash
vendor/bin/microunit init
```

```text
✔ Created microunit.config.php
✔ Created tests/example-tests.php

Run your tests with vendor/bin/microunit
```

`init` writes two files and never overwrites anything. If `microunit.config.php` already exists, it says so, changes nothing and exits with code `2`.

The config it writes is short on purpose:

```php
<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

// Every option: https://mitarnik04.github.io/MicroUnit/configuration
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->build();
```

### Framework detection

`init` reads your `composer.json` (both `require` and `require-dev`) and sets the config up for the framework it finds:

| Package in composer.json | What `init` adds |
| --- | --- |
| `laravel/framework` | `->withFramework(new LaravelIntegration())` and an example that requests `/` |
| `symfony/framework-bundle` | `->withFramework(new SymfonyIntegration())`, plus `->withBootstrapFile('tests/bootstrap.php')` when that file exists, and an example that checks the container |
| `codeigniter4/framework` | `->withFramework(new CodeIgniterIntegration())` |

Everything else gets the plain config above. The [frameworks](frameworks.md) page covers what the integrations do.

### Teaching your AI agent: `--skill`

```bash
vendor/bin/microunit init --skill
```

This also copies a skill file to `.claude/skills/microunit/SKILL.md`. Claude Code picks it up automatically; for other agents, `init` prints two lines to paste into your `AGENTS.md` (or whatever instructions file your agent reads). If the config already exists, `--skill` only installs the skill and exits with `0`, so you can run it on an existing project too. More on this in [AI coding agents](ai-agents.md).

## The first test

Here is the generated `tests/example-tests.php`:

```php
<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Example');

$tester->define('adds_two_numbers', function (): void {
    Assert::exact(4, 2 + 2);
});

$tester->define('refuses_to_divide_by_zero', function (): void {
    Assert::throws(fn() => intdiv(1, 0), DivisionByZeroError::class);
});
```

No class, no inheritance, no `test` prefix to remember. A test file asks for the tester of a suite, then defines tests as closures with a name that says what the code does. Assertions follow PHPUnit's argument order: expected first, actual second.

Two details matter:

- The file name ends in `-tests.php`. That is MicroUnit's default file pattern. If you prefer `*Test.php`, set it with `withTestFilePatterns('*Test.php')` (see [configuration](configuration.md#tests-and-discovery)).
- Your project's Composer autoloader is found and loaded for you. There is no bootstrap file to write just to get your own classes.

## Run it

```bash
vendor/bin/microunit
```

```text
-- Example --
[✔] adds_two_numbers (2.73ms)
[✔] refuses_to_divide_by_zero (0.03ms)

Summary: 2/2 passed, 0 failed in 20.56ms
```

MicroUnit finds `microunit.config.php` by walking up from the current directory, so you can run it from any subfolder of the project. Paths inside the config are resolved relative to the config file, not to where you happen to be standing.

## Reading the output

Each suite gets a header, each test one line:

| Marker | Meaning |
| --- | --- |
| `[✔]` | Passed. The time is the test body only. |
| `[✔] ... no assertions` | Passed without a single assertion. It proves nothing, so MicroUnit flags it. You can make this fail the run with [`failOnRiskyTests()`](configuration.md#ai-agents-and-strictness). |
| `[-] ... skipped: reason` | Skipped with `Assert::skip()` or `Assert::skipIf()`. Not a failure. |
| `[✖]` | Failed. The explanation follows, indented. |

Here is a failure:

```text
-- Cart --
[✖] sums_the_line_totals (3.32ms)
    Failed asserting that two values are identical (type and value).
    Expected: 31 (int)
    Actual  : 30 (int)

Summary: 0/1 passed, 1 failed in 19.43ms
```

For arrays and multi-line strings you get a diff instead of two blobs to compare by eye. An unexpected exception shows its class, message and the file and line it came from.

The summary line counts passed, failed and (if any) skipped tests, and the time is wall clock: what you actually waited for, including bootstrapping. The exit code is `0` when everything passed, `1` when something failed, and `2` for a usage or configuration error. The [CLI page](cli.md#exit-codes) has the full list.

The default output stays compact on purpose. It does not print what a test echoed or what it returned. When you want that, the Test Explorer shows it, or you can switch to the detailed `StringTestWriter` (see [output](output.md)).

## The Test Explorer

```bash
vendor/bin/microunit explorer
```

opens the Test Explorer in your browser: a local page with every test, its status, output and failure details, buttons to run all tests, a suite, a single test or only the failures, and links that open the file at the right line in your editor. Test runs in your terminal update it while it is open.

It is opt-in, so a normal run never opens a browser tab and does no work for it. `--explorer` opens it after a single run; `withTestExplorer()` in the config opens it after every interactive run. Details on the [Test Explorer](test-explorer.md) page.

## Next steps

- [Writing tests](writing-tests.md): suites, data sets, setUp and tearDown, fixtures, skipping.
- [Assertions](assertions.md): everything `Assert` can check.
- [Mocking](mocking.md) and [snapshots](snapshots.md) when plain assertions are not enough.
- [Configuration](configuration.md) and the [command line](cli.md) for the full reference.
- Coming from PHPUnit? `microunit migrate` converts your suite. See [Migrating from PHPUnit](migrating-from-phpunit.md).
