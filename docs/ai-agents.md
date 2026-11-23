---
layout: default
title: MicroUnit for AI agents
description: Compact JSON output for coding agents, an integrity check that catches weakened tests, risky-test detection and an installable agent skill.
---

# MicroUnit for AI agents

Coding agents run tests constantly: after every edit, sometimes dozens of times per task. That changes what a test runner should optimise for. Every line of output costs tokens and context, a wall of green checkmarks is pure noise, and the agent needs the failing line, the expected and actual values and nothing else.

There is also a less comfortable problem. Under pressure to turn a red test green, agents sometimes weaken the test instead of fixing the code.

MicroUnit has a feature for each of these: output made for agents, an integrity check that flags weakened tests, a warning for tests that prove nothing, and a skill file that teaches the agent how MicroUnit works.

## Agent output

In an agent session MicroUnit prints one compact JSON object instead of the console output. Passing tests cost nothing: they show up as a count, never as lines.

It switches on by itself when:

- `CLAUDECODE=1` is set (Claude Code sets it for every command it runs), or
- `MICROUNIT_AGENT=1` is set. That is the switch for every other agent: put it in the agent's environment, its shell profile or its instructions.

You can also decide explicitly:

```bash
vendor/bin/microunit --agent       # JSON for this run
vendor/bin/microunit --no-agent    # normal console output for this run
```

```php
->withAgentOutput()        // always JSON
->withAgentOutput(false)   // never, even in agent sessions
```

The flag wins over the config, and the config wins over the environment detection.

### What the JSON looks like

Here is a real run with two failing tests. The output is a single line; it is pretty-printed here so you can read it.

```json
{
  "result": "failed",
  "tests": 3,
  "passed": 1,
  "failed": 2,
  "skipped": 0,
  "seconds": 0.028,
  "failures": [
    {
      "test": "Cart::gives_ten_percent_off_above_100_euros",
      "at": "tests/cart-tests.php:22",
      "message": "Failed asserting that two values are identical (type and value).",
      "expected": "10000 (int)",
      "actual": "10800 (int)",
      "code": [
        "20|     $cart->add('desk', 12000);",
        "21| ",
        "22>     Assert::exact(10000, $cart->total());",
        "23| });"
      ]
    },
    {
      "test": "Cart::lists_the_item_names",
      "at": "tests/cart-tests.php:29",
      "message": "Failed asserting that two values are identical (type and value).",
      "diff": [
        "…",
        "      0 => 'book' (string),",
        "      1 => 'pen' (string),",
        "-     2 => 'lamp' (string),",
        "  ]"
      ],
      "code": [
        "27|     $cart->add('pen', 300);",
        "28| ",
        "29>     Assert::exact(['book', 'pen', 'lamp'], $cart->names());",
        "30| });"
      ]
    }
  ]
}
```

When everything passes, the whole output is one short line:

```json
{"result":"passed","tests":3,"passed":3,"failed":0,"skipped":0,"seconds":0.021}
```

The fields of a failure:

| Field | Content |
| --- | --- |
| `test` | The test id, `Suite::test_name`. Pass it to `--filter` to re-run just this test. |
| `at` | `file:line` of the failing line (relative to the project). |
| `message` | The assertion's message, including your custom message. |
| `expected`, `actual` | Both values, for scalars and short values. |
| `diff` | Instead of `expected`/`actual` for arrays, objects and multi-line strings: changed lines with two lines of context, long equal stretches folded into `…`. |
| `error` | For exceptions: `Class: message`. |
| `thrownAt` | Where the exception was thrown, when that is not the test file. |
| `trace` | Up to eight stack frames from your own code (no `vendor/`, no MicroUnit internals). |
| `code` | The failing line (marked with `>`) and two lines around it, so the agent often doesn't need to open the file. |
| `output` | Whatever the test printed. |

Long values are shortened to about 1500 bytes; what was left out is marked with `…`. Next to `failures`, the object can contain `warnings` (tests without assertions, see below), `weakenedTests` (the integrity check, when you enabled it) and `skippedTests` (with their reasons).

`result` always agrees with the exit code. When the run fails although every test passed, because of a coverage minimum, `failOnRiskyTests()` or an integrity check with `failOnFindings`, `result` is `"failed"` and `failedBecause` says why:

```json
{"result":"failed","tests":6,"passed":6,"failed":0,"skipped":0,"seconds":0.014,"failedBecause":["1 test(s) passed without a single assertion (failOnRiskyTests)"],"warnings":[{"test":"Risky::asserts_nothing","warning":"passed without a single assertion"}]}
```

In agent sessions, the coverage table goes to standard error, so standard output carries only the JSON.

### Which writers stay active

In an agent session, the console writers (`MinimalStringTestWriter`, `StringTestWriter`) make way for the JSON. Writers that write files, like the JUnit or HTML writer, keep working, and GitHub Actions annotations are not added. See [Output](output.md) for the writers themselves.

### Writing the JSON to a file

`AgentTestWriter` is an ordinary writer, so you can also configure it yourself, for example to hand the result of a CI run to an agent:

```php
use MicroUnit\Output\AgentTestWriter;
use MicroUnit\Output\MinimalStringTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withTestWriters(
        new MinimalStringTestWriter(),
        new AgentTestWriter('build/test-results.json'),
    )
    ->build();
```

The path is relative to the working directory. Paths inside the JSON are relative to the working directory too, or to `projectDirectory:` if you pass it.

If you configure an `AgentTestWriter` that writes to standard output, MicroUnit uses yours in agent sessions instead of adding its own. One that writes to a file keeps writing its file, and the agent still gets the JSON on standard output.

## The integrity check

This is the feature I'd most like other test runners to copy. An agent that can't make a test pass has a few tempting shortcuts: delete the assertion, change the expected value to whatever the code returns, skip the test, or replace the check with `Assert::isTrue(true)`. The test goes green and the bug stays. In a long diff, that is easy to miss in review.

The integrity check compares your test files with a git revision and lists tests that were weakened. It is opt-in: nothing runs unless you call `microunit integrity` or configure `withIntegrityCheck()`, so projects without git, or teams that don't want it, never notice it exists.

| Finding | Example |
| --- | --- |
| `test removed` | a test that existed at the revision is gone |
| `assertions removed (2 before, 1 now)` | the test makes fewer assertions than before |
| `now skipped` | the test gained an `Assert::skip()` or `Assert::skipIf()` |
| `expected value changed: 10000 → 10800` | a literal expected value (first argument of `exact()`, `equals()` and similar) changed |
| `data sets removed (2 before, 1 now)` | a `defineGroup()` lost data sets |
| `assertion that cannot fail: Assert::isTrue(true)` | a new assertion that is always true, like `isTrue(true)`, `isNull(null)` or `exact($x, $x)` |

### Running it by hand

```bash
vendor/bin/microunit integrity              # compared to HEAD (your uncommitted changes)
vendor/bin/microunit integrity origin/main  # everything this branch changed
```

```text
! tests/cart-tests.php:3 Cart::sums_the_prices: assertions removed (2 before, 1 now)
! tests/cart-tests.php:7 Cart::gives_ten_percent_off_above_100_euros: expected value changed: 10000 → 10800

2 finding(s) compared to HEAD. Each one asks for a look; none of them proves a mistake.
```

It exits with `1` when it found something, `0` when it didn't (`✔ No weakened tests compared to HEAD.`) and `2` when the directory is not a git repository or the revision does not exist. It reads the configured test directories and groups, compares the working tree (including uncommitted changes) with the revision, and runs no tests.

### After every run

Configure the check and it runs after every test run:

```php
->withIntegrityCheck()                                    // against HEAD: your uncommitted changes
->withIntegrityCheck('origin/main', failOnFindings: true) // e.g. in CI for pull requests
```

For an agent working on your code, `withIntegrityCheck()` is the setting to use: every run then compares against the last commit. In an agent session the findings appear in the JSON:

```json
"weakenedTests": [
  {"test": "Cart::gives_ten_percent_off_above_100_euros", "at": "tests/cart-tests.php:7", "finding": "expected value changed: 10000 → 10800"}
],
"weakenedTestsNote": "Compared to the last commit. Tests must not be weakened to make them pass: fix the code, or explain why the test changed."
```

When the revision is not `HEAD`, the note names it (`Compared to origin/main.`). The note is written for the agent: at the moment it matters, it says that this is not an acceptable way to get a green run, and what to do instead. The agent skill described below says the same thing up front.

In console runs, the findings are printed below the summary.

With `failOnFindings: true`, any finding makes the run exit with `1`, and so does a check that could not compare at all (no git repository, or a revision missing from a shallow clone), so a misconfigured pipeline can't pass by accident. That works well as a pull request gate: the author either fixes the code or explains the changed test in the review.

### What it can't do

These are heuristics that read your test source. They are useful precisely because they are cheap and run all the time. Here is where they stop:

- **A finding is a question, not a verdict.** Removing an obsolete test or correcting a wrong expected value is legitimate. That is why findings don't fail the run unless you ask for it.
- **Only modified and deleted test files are compared.** A new test file has nothing to compare against. A test that was moved to another file or renamed shows up as removed.
- **Production code is not checked.** An agent that "fixes" a test by special-casing the test input in the code under test will not be caught here. Code review still matters.
- **Only recognisable assertions count**: `Assert::` and `AssertMock::` calls, chained mock checks (`->isCalled...()`) and helper functions whose name starts with `assert`. Expected-value changes are only reported between literals (numbers, strings, `true`, `false`, `null`): a literal that disappeared is paired with one that appeared, so adding or reordering assertions is not reported as a change.
- **It needs git** and the git command line on the `PATH`. When a configured check cannot run, MicroUnit says so on standard error.

## Risky tests

A test that passes without a single assertion proves nothing. It may be a smoke test on purpose, or an agent may have written a test that calls the code and checks nothing. MicroUnit marks such tests in the console output (`no assertions` after the test name) and lists them under `warnings` in the agent JSON:

```json
"warnings": [{"test": "Report::renders_without_crashing", "warning": "passed without a single assertion"}]
```

To make them fail the run (exit code `1`), turn on the strict mode:

```php
->failOnRiskyTests()
```

```text
1 test(s) passed without a single assertion (failOnRiskyTests).
```

## Teaching the agent MicroUnit: `init --skill`

Agents know PHPUnit from their training data and will happily write `class FooTest extends TestCase` into a MicroUnit project. A short instruction file fixes that:

```bash
vendor/bin/microunit init --skill
```

```text
✔ Installed the MicroUnit skill for AI agents: .claude/skills/microunit/SKILL.md
  Other agents: add to AGENTS.md (or your agent's instructions file):
  ## Tests
  Tests use MicroUnit. Read .claude/skills/microunit/SKILL.md before writing or running tests.
```

Claude Code picks up skills from `.claude/skills/` on its own. For Codex, Cursor, Copilot and other agents, paste the two printed lines into `AGENTS.md` or whatever instructions file your agent reads.

The skill is one page: how to run tests (with `--filter`, `--failed`, `--list=json`), how to write them (closures, fixtures, data sets, PHPUnit argument order), the assertions worth knowing, mocks, framework helpers, and a short "never" list that starts with "never weaken a test to make it pass". Commit it with your project so every agent and every teammate's agent gets it.

`init --skill` works in projects that are already set up: if `microunit.config.php` exists, it only installs the skill. Run it again after upgrading MicroUnit to replace the file with the version that ships with the new release (it overwrites the existing skill file, so keep your own additions in `AGENTS.md`).

## Commands agents should know

**Discover tests without running them.** `--list=json` prints one JSON object per line, one line per selected test:

```bash
vendor/bin/microunit --list=json --filter=Cart
```

```json
{"id":"Cart::sums_the_prices","suite":"Cart","name":"sums_the_prices","group":null,"file":"/app/tests/cart-tests.php","line":12}
```

`group` is the base name of a `defineGroup()` test for its data sets, `null` otherwise. Plain `--list` prints just the ids.

**Re-run only what failed.** `--failed` runs the tests that failed in the previous run, which makes the fix loop short:

```bash
vendor/bin/microunit                     # full run: 2 failures
vendor/bin/microunit --failed            # after the fix: just those 2
vendor/bin/microunit                     # one full run before finishing
```

If nothing failed last time, `--failed` says so and runs everything. Add `--stop-on-failure` to stop at the first failure when the agent fixes one thing at a time.

**Target precisely.** `--filter=Cart::adds` matches test ids by substring, `--filter=/regex/` by regular expression, and paths run single files or directories: `vendor/bin/microunit tests/Unit/CartTest.php`.

## Related pages

- [Output](output.md) for every writer, including the JUnit and GitHub Actions ones.
- [CLI](cli.md) for all flags.
- [Writing tests](writing-tests.md) for the test syntax the skill teaches.
- [Benchmarks](benchmarks.md) for why per-run overhead matters when an agent runs tests fifty times an hour.
- [Troubleshooting](troubleshooting.md#json-output-appears-when-you-did-not-ask-for-it) if JSON shows up in your terminal unexpectedly.
