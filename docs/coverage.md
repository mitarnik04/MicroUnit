---
layout: default
title: Code coverage
description: Measure line coverage in pure PHP without Xdebug or PCOV, write text, HTML, Clover and Cobertura reports, and fail the build below a minimum.
---

# Code coverage

MicroUnit measures code coverage without Xdebug, PCOV or phpdbg. There is nothing to install and nothing to enable in `php.ini`: the same PHP that runs your tests measures them. It instruments your source code on the fly, counts which statements ran, and writes the usual reports (a console table, browsable HTML, Clover XML and Cobertura XML). This page covers how that works, how to configure it, and where its limits are.

## Quick start

For a single run, add `--coverage`:

```bash
vendor/bin/microunit --coverage
```

```text
-- Cart --
[✔] sums_the_prices (4.58ms)

Summary: 1/1 passed, 0 failed in 27.03ms

Code coverage
  Lines:      75.0% (6/8)
  Functions:  33.3% (1/3)

  src/Cart.php   75.0% (6/8)
```

Without any configuration, `--coverage` measures everything under `src/` (next to `microunit.config.php`, or in the working directory when there is no config file) and prints the table. The table lists totals first, then every file that is not fully covered; fully covered files are summed up in one line (`+ 12 file(s) fully covered`) so the table stays short on a healthy project.

## Configuring it

To measure coverage on every run, configure it with `withCoverage()`. Every argument is optional, so named arguments read best:

```php
use MicroUnit\Config\MicroUnitConfigBuilder;

return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withCoverage(
        include: 'src',
        exclude: ['src/Legacy', 'src/Kernel.php'],
        html: 'build/coverage',
        clover: 'build/clover.xml',
        minimum: 80,
    )
    ->build();
```

| Argument | Default | What it does |
| --- | --- | --- |
| `include` | `'src'` | Directory or file (or a list of them) whose code is measured. |
| `exclude` | `[]` | Directories or files inside `include` to leave out. |
| `html` | `null` | Directory for the HTML report. |
| `clover` | `null` | File for the Clover XML report. |
| `cobertura` | `null` | File for the Cobertura XML report. |
| `text` | `true` | Print the coverage table to the console after the run. |
| `minimum` | `null` | Fail the run when line coverage is below this percentage. |

All paths are relative to the config file; absolute paths work too. Only `.php` files are measured.

Once coverage is configured, it runs every time. Two flags override the config for one run:

- `--no-coverage` skips it, for a quick run while you work.
- `--coverage` turns it on when the config doesn't (using the configured settings if there are any, `src/` and the table otherwise).

A common split: no `withCoverage()` locally, and `vendor/bin/microunit --coverage` in CI with the reports configured. Or the reverse, `withCoverage()` in the config and `--no-coverage` while iterating.

## Reports

**Text.** The console table shown above, coloured green from 80%, yellow from 50%, red below. Turn it off with `text: false` when you only want the files.

**HTML.** `html: 'build/coverage'` writes an overview page (`index.html`) and one page per source file with every line marked as covered or not. It works offline: no CDN, no JavaScript framework, just files you can open, zip or upload as a CI artifact. The console prints the path after the run. When the [Test Explorer](test-explorer.md) is open, its "Coverage report" link serves the latest HTML report.

**Clover.** `clover: 'build/clover.xml'` is the format Codecov, Coveralls, Jenkins and SonarQube read.

**Cobertura.** `cobertura: 'build/cobertura.xml'` is what GitLab merge requests and Azure DevOps expect.

You can configure any combination of them at the same time. The [CI/CD](ci-cd.md) page shows how to hand them to the common CI systems.

## Enforcing a minimum

```php
->withCoverage(minimum: 80)
```

When line coverage ends up below the minimum, MicroUnit says so and exits with code `1`, even if every test passed:

```text
  Line coverage 75.0% is below the required minimum of 80.0%.
```

The minimum applies to line coverage of the whole run, not per file and not to function coverage. The [Test Explorer](test-explorer.md) shows the same percentage in its readout.

## How it works

Classic PHP coverage tools hook into the engine through an extension. MicroUnit does it in userland, in three steps:

1. **Intercepting includes.** When coverage is on, MicroUnit registers a stream wrapper for `file://`. Every `include` and `require` of a file inside `include` (and not in `exclude`) receives an instrumented copy of the file instead of the original. Every other file operation goes straight through to PHP.
2. **Instrumenting statements.** A small parser on top of PHP's own tokenizer puts a hit counter in front of every executable statement: assignments, calls, `return`, `if`, loops, `throw`, and so on, inside functions, methods, closures, arrow functions and property hooks. The counter is placed on the statement's own line, so line numbers, `__FILE__` and `__DIR__` stay exactly those of the original file. Stack traces and failure lines keep pointing at the right place.
3. **Counting.** A counter is one increment on a static array, about the cheapest thing PHP can do, so instrumented code stays fast. After the run, the counters become the reports.

A line is **executable** when a statement starts on it, and **covered** when that statement started running. A function or method counts as covered when all of its executable lines ran (the same definition PHPUnit uses). Files under `include` that no test loaded still show up, with zero coverage, so untested code lowers the percentage as it should instead of silently vanishing from the report.

Instrumentation is cached in `.microunit/cache/coverage`, keyed by each file's path, size, modification time and the version of the instrumenter. On the next run, unchanged files skip parsing entirely. You never need to clear the cache by hand; deleting `.microunit` is safe if you want to anyway.

[Parallel runs](parallel.md) measure coverage in every worker and merge the results, so `--parallel --coverage` just works.

## The OPcache caveat

With `opcache.enable_cli=1`, OPcache may serve a file's cached bytecode instead of asking the stream wrapper for it, and then the instrumented code never runs. MicroUnit detects this and warns on stderr:

```text
Coverage: OPcache is enabled for the CLI and may bypass instrumentation. Run PHP with -d opcache.enable_cli=0.
```

If you see it, and especially if coverage numbers look too low, run the coverage job with OPcache disabled for the CLI. Most PHP installations ship with `opcache.enable_cli=0`, so this mainly affects Docker images and hand-tuned `php.ini` files.

## Limits

Being honest about what a userland approach can and can't do:

- **Line coverage only.** There is no branch or path coverage. The Cobertura report always says `branch-rate="0"`.
- **Statement granularity.** A statement spread over several lines counts on the line where it starts. A line with two statements is covered as soon as either of them ran.
- **Only included files.** Code reaches the counters through `include`/`require`. Code run through `eval()` is not measured, and neither are files loaded before coverage started. That happens when `microunit.config.php` itself includes source files; MicroUnit warns about it (`Coverage: N file(s) were loaded before coverage started and cannot be measured`). Keep the config file free of `require` statements for your own code and use the bootstrap file instead.
- **Unknown syntax is skipped, not guessed.** If the instrumenter meets syntax it can't parse, it leaves that file out of the report and says so on stderr (`Coverage: /path/to/src/Weird.php is not measured (...)`). The file still runs normally, it just isn't counted.
- **Overhead.** While coverage is on, file operations in your tests pass through the stream wrapper, and instrumented code does a little more work per statement. Expect coverage runs to be slower than plain runs, which is one more reason to use `--no-coverage` for quick local loops.
- **Needs the tokenizer extension.** It is part of every standard PHP build, but some minimal Docker images leave it out.

## Related pages

- [CI/CD](ci-cd.md) for uploading Clover or Cobertura reports.
- [Parallel runs](parallel.md) for coverage across workers.
- [Configuration](configuration.md) for every option.
- [Troubleshooting](troubleshooting.md#coverage-is-zero-or-suspiciously-low) when the numbers look wrong.
