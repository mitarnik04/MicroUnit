---
layout: default
title: Output and reports
description: Console output, JUnit, HTML, logs, GitHub annotations and JSON for AI agents, plus how to write your own test writer.
---

# Output and reports

Everything MicroUnit reports goes through test writers. A writer receives the results and decides what to do with them. It might print a line per test, write a JUnit file for your CI, render an HTML report for a reviewer, or produce a compact JSON object for an AI agent.

You can run several writers at once, and writing your own takes four methods. Out of the box you get one: a compact, coloured console output that shows details only for the tests that need your attention.

## Choosing writers

Writers are configured in `microunit.config.php`. `withTestWriters()` sets the full list, `addTestWriter()` appends one:

```php
use MicroUnit\Config\MicroUnitConfigBuilder;
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

`addTestWriter()` adds to the default console output, so this keeps the console and writes a JUnit file:

```php
->addTestWriter(new JUnitTestWriter('build/junit.xml'))
```

`withTestWriters()` replaces the list, the default included. Put `MinimalStringTestWriter` (or `StringTestWriter`) in it if you still want console output.

Two writers join in automatically, without configuration:

- `GitHubActionsTestWriter` when the run happens on GitHub Actions (details [below](#githubactionstestwriter)).
- `AgentTestWriter` when an AI coding agent runs the tests. In that case the two console writers step aside, and file writers like JUnit keep working. See [AI coding agents](ai-agents.md).

File paths given to writers are resolved against the current working directory, not the config file. Run MicroUnit from your project root (you almost certainly do anyway) or pass absolute paths.

## MinimalStringTestWriter

The default. One line per test, details only for failures:

```
-- Cart --
[✔] adds_items (0.21ms)
[✔] applies_the_discount (0.05ms)
[✖] refuses_negative_quantities (0.11ms)
    Failed asserting that two values are identical (type and value).
    Expected: 'Quantity must be positive.' (string)
    Actual  : 'Invalid quantity.' (string)

[-] syncs_with_the_warehouse skipped: WAREHOUSE_URL is not set.

Summary: 2/4 passed, 1 failed, 1 skipped in 12.40ms
```

A test that passes without making a single assertion gets a yellow `no assertions` marker. It proved nothing, and you should know. ([CI/CD](ci-cd.md#fail-on-risky-tests) shows how to make that fail the run.)

### Colours

Colour is on by default. MicroUnit turns it off when:

- `NO_COLOR` is set (to anything non-empty), or
- the output goes to a pipe or file, unless you're on a CI system whose log viewer renders colours. GitHub Actions, GitLab CI, Azure Pipelines, CircleCI, Buildkite, TeamCity and Drone are detected, so CI logs stay colourful.

`FORCE_COLOR` forces colour on. For a single run, `--colors` and `--no-colors` do the same thing. To decide in the config instead, pass `colors` to the writer, which overrides all of the above:

```php
new MinimalStringTestWriter(colors: false)
```

## StringTestWriter

The detailed console format: for every test, also its return value, captured output, location and, for errors, the stack trace. Useful when you're digging into a single suite; too noisy as a daily driver.

```php
->withTestWriters(new StringTestWriter())
```

It takes the same `colors` argument as `MinimalStringTestWriter`.

## FileTestWriter

Writes the console output, without colours, to a file. The file is written once, after the run.

```php
new FileTestWriter('build/test-results.log', clearFileBeforeRun: true, detailed: false)
```

| Argument | Default | Meaning |
| --- | --- | --- |
| `filePath` | `'test-results.log'` | Where to write. |
| `clearFileBeforeRun` | `false` | `false` appends each run to the file, `true` replaces it. |
| `detailed` | `false` | Use the `StringTestWriter` format instead of the minimal one. |

## JUnitTestWriter

JUnit XML is the lingua franca of CI systems: GitHub Actions (through a reporter action), GitLab, Jenkins, Azure DevOps and CircleCI all read it to show test results in their UI.

```php
new JUnitTestWriter('build/junit.xml') // default: 'junit.xml'
```

Each suite becomes a `<testsuite>`, each test a `<testcase>` with the suite as `classname`, the duration, and the file and line where it failed. Failed assertions are reported as `<failure>`, unexpected exceptions as `<error>` with the stack trace, skipped tests as `<skipped>` with the reason. Captured output goes into `<system-out>`, and a test's return value into a `returnValue` property.

## GitHubActionsTestWriter

On GitHub Actions, every failing test becomes an annotation on the failing line, visible in the pull request's diff and on the run's summary page. Nobody has to dig through the log to find out which assertion broke.

You don't configure this. MicroUnit adds the writer when `GITHUB_ACTIONS=true`, which GitHub sets on every runner. Paths are made relative to the workspace so GitHub can link them. Passed and skipped tests produce no annotations.

To turn it off:

```php
->withoutCiAnnotations()
```

## HtmlTestWriter

A self-contained HTML report: the verdict first, then every failure with its reason, the skipped tests, and the full list of tests. Styles and fonts are inlined and there's no JavaScript, so the single file works as a CI artifact, as an email attachment and on paper.

```php
new HtmlTestWriter('build/test-report.html') // default: 'test-report.html'
```

Each failure shows the file and line where it happened, the message, expected and actual values, and the stack trace for errors.

For the interactive version with filters, reruns and coverage, use the [Test Explorer](test-explorer.md).

## LogTestWriter

A structured log of the run, one line per test plus a summary line. The line format is a template, so the log can look exactly like the other logs your system produces.

```php
new LogTestWriter('build/microunit.log', logPassedTests: false)
```

```
[2026-11-19T09:14:02+00:00] microunit.ERROR: FAILED Cart > refuses_negative_quantities (0 ms) - Failed asserting that two values are identical (type and value).
[2026-11-19T09:14:02+00:00] microunit.WARNING: SKIPPED Cart > syncs_with_the_warehouse (0 ms) - WAREHOUSE_URL is not set.
[2026-11-19T09:14:02+00:00] microunit.ERROR: SUMMARY  >  (12 ms) - 2/4 passed, 1 failed
```

| Argument | Default | Meaning |
| --- | --- | --- |
| `filePath` | `'microunit.log'` | Where to write. Runs are appended. |
| `lineFormat` | `LogTestWriter::DEFAULT_LINE_FORMAT` | The template, see below. |
| `dateFormat` | `'Y-m-d\TH:i:sP'` | Any `date()` format for `{datetime}`. |
| `logPassedTests` | `true` | `false` logs only failures, skips and the summary. |

The default template is `[{datetime}] microunit.{level}: {status} {suite} > {test} ({duration_ms} ms){failure}`. Available placeholders:

| Placeholder | Value |
| --- | --- |
| `{datetime}` | When the line was written, in `dateFormat`. Results are written per suite once the suite finished (in a parallel run, once every worker finished), so this is not the moment the single test ran. |
| `{level}` | `INFO` (passed), `WARNING` (skipped), `ERROR` (failed or a failing summary), `NOTICE` (passing summary). |
| `{status}` | `PASSED`, `SKIPPED`, `FAILED` or `SUMMARY`. |
| `{suite}`, `{test}`, `{id}` | Suite name, test name, and the full id `Suite::test`. |
| `{duration_ms}` | Duration in milliseconds (the total for the summary). |
| `{message}` | Failure message or skip reason, on one line. |
| `{failure}` | ` - {message}` when there is a message, otherwise empty. |
| `{file}`, `{line}` | Where the test is defined, or where it failed. |

A format that matches Laravel's log, for example:

```php
new LogTestWriter(
    'storage/logs/tests.log',
    lineFormat: '[{datetime}] testing.{level}: {suite}::{test} {status}{failure}',
    dateFormat: 'Y-m-d H:i:s',
)
```

### JSON lines

For log shippers (Filebeat, Fluent Bit, Vector and friends), `LogTestWriter::jsonLines()` writes one JSON object per line with all the fields above:

```php
LogTestWriter::jsonLines('build/microunit.jsonl', logPassedTests: true)
```

```json
{"datetime":"2026-11-19T09:14:02+00:00","level":"ERROR","status":"FAILED","suite":"Cart","test":"refuses_negative_quantities","id":"Cart::refuses_negative_quantities","duration_ms":0,"message":"Failed asserting that two values are identical (type and value).","file":"/app/tests/cart-tests.php","line":31}
```

## AgentTestWriter

AI coding agents pay for every token they read, and a wall of green checkmarks is pure waste. `AgentTestWriter` prints one compact JSON object with the failures only. Each failure carries what an agent needs to fix it and nothing else:

- where it failed
- expected and actual, or a diff
- the code around the failing line
- a stack trace limited to your project's own code

```json
{"result":"failed","tests":11,"passed":8,"failed":1,"skipped":2,"seconds":1.214,"failures":[{"test":"Cart::refuses_negative_quantities","at":"tests/cart-tests.php:31","message":"Failed asserting that two values are identical (type and value).","expected":"'Quantity must be positive.' (string)","actual":"'Invalid quantity.' (string)","code":["30|     $cart = new Cart();","31>     Assert::exact('Quantity must be positive.', $cart->add('A1', -1));"]}],"skippedTests":[{"test":"Cart::syncs_with_the_warehouse","reason":"WAREHOUSE_URL is not set."}]}
```

You normally don't configure it. It switches on by itself when an agent runs the tests. Claude Code is detected, and any other agent can set `MICROUNIT_AGENT=1`. You can also force it with `--agent` or `withAgentOutput()`. The [AI coding agents](ai-agents.md) page covers that, plus the warnings and integrity findings in the JSON.

Configure it explicitly to write the JSON to a file instead of stdout:

```php
new AgentTestWriter('build/agent.json')
```

The optional second argument, `projectDirectory`, sets the directory that paths in the output are relative to. It defaults to the working directory.

A file-based `AgentTestWriter` writes its file in every run. In agent sessions, MicroUnit still adds its own stdout writer next to it, so the agent gets the JSON on the console as well. Only a configured stdout `AgentTestWriter` (no file path) replaces MicroUnit's own.

## Writing your own writer

A writer is any class implementing `MicroUnit\Output\ITestWriter`. Four methods:

| Method | When MicroUnit calls it |
| --- | --- |
| `writeSuite(string $suite)` | Once per suite, after the suite finished, right before `writeResults()`. |
| `writeResults(array $results)` | Once per suite, with that suite's `TestResult` list. |
| `writeResult(TestResult $result)` | Never directly. It exists for your `writeResults()` to call per result. |
| `writeSummary(int $totalTests, int $successes, int $failures)` | Once, after all tests ran. |

Here's a writer that lists slow tests at the end of a run:

```php
<?php

use MicroUnit\Core\TestResult;
use MicroUnit\Output\ITestWriter;

final class SlowTestWriter implements ITestWriter
{
    /** @var list<TestResult> */
    private array $slow = [];

    public function __construct(private readonly float $thresholdInSeconds = 0.5) {}

    public function writeSuite(string $suite): void {}

    public function writeResults(array $results): void
    {
        foreach ($results as $result) {
            $this->writeResult($result);
        }
    }

    public function writeResult(TestResult $result): void
    {
        if ($result->time >= $this->thresholdInSeconds) {
            $this->slow[] = $result;
        }
    }

    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        foreach ($this->slow as $result) {
            printf("SLOW %s (%.2f s)\n", $result->id, $result->time);
        }
    }
}
```

```php
require_once __DIR__ . '/tests/Support/SlowTestWriter.php';

return MicroUnitConfigBuilder::create()
    ->withTestWriters(new MinimalStringTestWriter(), new SlowTestWriter(thresholdInSeconds: 0.2))
    ->build();
```

What a `TestResult` gives you:

| Property / method | Meaning |
| --- | --- |
| `id`, `suite`, `testName` | `Suite::test`, the suite, the test name. |
| `status` | `TestStatus::Passed`, `Failed` or `Skipped`. |
| `isSuccess`, `isSkipped()`, `isRisky()` | Not failed / skipped / passed without assertions. |
| `time` | Duration of the test body in seconds. |
| `assertions` | Number of assertions made. |
| `errorMsg` | Failure message or skip reason; `null` for passed tests. |
| `assertionFailure` | For failed assertions: `message`, `expected`, `actual`, `diff`. |
| `error` | For unexpected exceptions: `class`, `message`, `file`, `line`, `trace`. |
| `skipReason` | Why the test was skipped. |
| `output` | Everything the test printed. |
| `result` | The test function's return value. |
| `file`, `line`, `failureLine` | Where the test is defined and where it failed. |

Things worth knowing before you ship one:

- Writers always run in the main process, also during [parallel runs](parallel.md). Results arrive suite by suite, but in parallel runs `exception` (the live `Throwable`) is `null` and `result` is an exported copy, because they crossed a process boundary. Use `error` and `assertionFailure` instead.
- Writing a file? Do it in `writeSummary()`, once. The built-in file writers buffer and write at the end, so a crashed run never leaves half a report behind.
- In agent sessions only the two console writers are removed. A custom writer that prints to stdout will end up in the middle of the agent's JSON, so write to a file or check `AgentTestWriter::isAgentSession()`.

## Run logs

Writers report test results. PHP's own warnings, notices and deprecations are a different channel. MicroUnit routes them into a run log instead of letting them spray across the console output.

Run logs live in `.microunit/logs/`, one file per run named after the start time and process id, like `2026-11-19T09-14-02Z-3092.log`. A file only appears when PHP reported something, and a parallel run writes one per process. The runtime directory moves with `withRuntimeDirectory()`, and it git-ignores itself.

```
[19-Nov-2026 09:14:03 UTC] PHP Warning:  Undefined array key "missing" in /app/tests/cart-tests.php on line 5
```

Warnings don't fail tests, so this is where you look when something smells off.

By default only the latest run's logs are kept. To keep all of them:

```php
->persistRunLogs()
```

If a run dies on a fatal error, MicroUnit prints the error, its location and the path to the full log to stderr, so you're not left staring at a silent exit.

## Related

- [CI/CD](ci-cd.md): wiring JUnit, HTML and annotations into your pipeline.
- [AI coding agents](ai-agents.md): agent output, integrity findings and the agent skill.
- [Test Explorer](test-explorer.md): the interactive report.
- [Configuration](configuration.md): every option on `MicroUnitConfigBuilder`.
