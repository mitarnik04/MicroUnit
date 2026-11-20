---
layout: default
title: Test Explorer
description: The browser-based Test Explorer that opens after interactive runs, shows every test with its failure details and re-runs any selection of tests.
---

# Test Explorer

The Test Explorer is a small web UI that MicroUnit serves on your own machine. If you have used the Test Explorer in Visual Studio, you already know the idea: every test in one list, a summary of what passed and what didn't, a detail pane for the selected test, and a handful of ways to run exactly the tests you care about right now. It is opt-in: it opens when you ask for it, it needs nothing installed (it is plain PHP plus one HTML file), and a run without it costs nothing for it.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="{{ '/assets/img/explorer-dark.png' | relative_url }}">
  <img alt="The MicroUnit Test Explorer: the test list on the left, a failed assertion with its diff on the right" src="{{ '/assets/img/explorer.png' | relative_url }}" width="100%">
</picture>

## Opening it

The explorer is off until you ask for it, so a plain test run does no work for it at all. Three ways to open it:

```bash
vendor/bin/microunit explorer     # open it now and run tests from the browser
vendor/bin/microunit --explorer   # run the tests, then open it with the results
```

Or open it after every local run, in the config:

```php
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withTestExplorer()
    ->build();
```

Everything about the explorer is set in that one call:

```php
->withTestExplorer(
    openAfterRun: true,   // open it (or update the open one) after every interactive run
    port: 7357,           // the local port it listens on
    editor: 'phpstorm',   // what its file links open
)
```

With `openAfterRun: false`, the port and editor still apply when you open it with `microunit explorer`.

`microunit explorer` starts the server if it isn't running and opens the browser. Before the first run there is nothing to show yet, so you get an empty page with a "Run all" button. Once it is open, it stays current: test runs in your terminal update the open tab too, and the terminal only prints the URL:

```text
Test Explorer updated: http://127.0.0.1:7357/
```

With `withTestExplorer()` it still never opens by itself when either of these is true:

- the `CI` environment variable is set (GitHub Actions, GitLab CI and most other CI systems set it), or
- standard output is not a terminal, for example when you pipe the output into a file or another program, or when an AI agent runs the tests.

`--no-explorer` keeps it closed for a single run even when the config turns it on.

### Port

The explorer listens on port `7357` by default. If that port is taken (by another project's explorer, for example), pick a different one:

```php
->withTestExplorer(port: 7358)
```

Each project remembers its own running server, so two projects with two ports can have their explorers open side by side.

## What you see

The explorer is built like the test runner in an IDE: a toolbar, a list of your tests on the left, the details of the selected one on the right, and a status bar. It is dark by default; the theme switch in the status bar cycles through dark, light and following your system, and your browser remembers the choice.

**The toolbar** has the run actions (Run all, Rerun failed, the Run menu), a text filter (matches anywhere in `Suite::test`) and the status filter with counts: All, Failed, Passed, Skipped, Not run.

**The run bar** under it is a thin strip with one cell per test, in the order they were defined: green passed, red failed, amber skipped, grey not run, dimmed when the result is from an earlier run. On a big suite it is the fastest way to spot the one red cell among a thousand green ones; click a cell to select that test. Above 600 tests it shows one segment per status instead. The line below it says how the run went ("2 failed", "All passed"), the counts, the number of tests, the wall-clock time and, when the run measured [coverage](coverage.md), the line coverage with a link to the HTML report.

**The list** shows your tests grouped by suite, in definition order. Data sets of a `defineGroup()` test sit under their own collapsible group. Every row has a drawn status icon (so the status never depends on colour alone), the name and the duration; suites and groups show how many tests they contain, their total time and, for suites, a "2 failed" badge. The sort button in the list's header puts the slowest tests first; the other two buttons expand or collapse everything. Drag the divider between list and details to make the list wider (double-click it to reset).

**The details** of a test show:

- its suite, its location as `file:line` and its duration,
- for a failed assertion: your custom message, the failure message, and either a diff of expected and actual (for arrays, objects and multi-line strings) or the two values,
- for an error: the exception class and message, where it was thrown, and the stack trace, with the frames in your project as links and the frames outside it (vendor code, MicroUnit itself) folded into one line you can expand,
- the skip reason for skipped tests,
- anything the test printed (Output) and the value it returned, if any. Very long output and stack traces are cut after 100 kB, with the number of bytes left out.

Every block has a copy button. When a test failed, the location points at the failing line, not at the start of the test.

Select a suite or a group instead, and the details show what is in it: the failed tests first, each with its failure message, then the others, every one a click away, plus buttons to run all of them or only the failed ones.

### Open in editor

Every `file:line` in the details is a link that opens your editor at that line. `E` or a double-click does the same for the selected test. VS Code is the default; choose yours in the config:

```php
->withTestExplorer(editor: 'phpstorm')
```

Known names are `vscode`, `cursor`, `windsurf`, `zed`, `phpstorm`, `idea` and `sublime`. Anything else must be a URL template containing `{file}` (and usually `{line}`):

```php
->withTestExplorer(editor: 'myeditor://open?file={file}&line={line}')
```

A name MicroUnit does not know and that contains no `{file}` falls back to VS Code, so check the spelling if the links open the wrong editor.

## Running tests from the explorer

The explorer is not just a viewer. Every run it starts is an ordinary `microunit` process with your config, started in the background so the page stays responsive. While a run is in progress, the tests it covers show a ring, the numbers of the last run are dimmed, and the run buttons are disabled until it finishes (one run at a time).

| Run mode | How | What runs |
| --- | --- | --- |
| Run all | button or `R` | every test |
| Rerun failed | button or `F` | the tests that failed in the latest results (same as `--failed`) |
| Run selection | `Enter`, or Run menu | the marked tests; otherwise the selected test, or the whole suite or group when one is selected |
| Run a suite or group | play button on its row, `Enter` on it, or right-click | every test of that suite, every data set of that group |
| Rerun | button in the details | the selected test |
| Run tests in view | `V`, or Run menu | every test that matches the current text and status filter |
| Run not-run tests | `N`, or Run menu | tests that have never run (status "Not run") |
| Run passed tests | `P`, or Run menu | tests that passed last time, handy after a refactoring |
| Repeat last run | `L`, or Run menu | whatever you ran last from this page |

The play button on a row appears when you hover over it. The Run menu (`M`) shows how many tests each mode would run and greys out the ones with nothing to do.

A "group" here is a `defineGroup()` test and its data sets. It is not the same thing as a test group from `withTestGroup()`, which you select on the command line with `--group`.

### Selecting several tests

- **Click** selects a test and clears any marks.
- **Ctrl+click** (Cmd+click on macOS) marks or unmarks a test without moving the selection.
- **Shift+click** marks every visible test between the selected one and the one you clicked.
- **Space** marks or unmarks the selected test, so the arrow keys plus `Space` build a selection without the mouse.

Marked tests get a tinted background. `Enter` then runs exactly those. `Esc` clears the marks (and the text filter).

### Partial runs keep earlier results

When a run covers only some tests (run selected, run rejects, `--filter`, `--failed`), the tests that did not run keep their previous result instead of turning grey. They are marked as stale: dimmed in the list and the run bar, and the details say "Not part of the latest run; this is the previous result." The verdict line shows "partial run" next to the time of the last run. You keep the full picture while you work on a handful of tests, and a full run clears every stale mark.

One caveat: this only covers tests MicroUnit loaded. When you run specific files or directories (`microunit tests/Unit`) or a `--group`, only those test files are loaded, so the explorer shows only their tests. Runs started from the explorer always load the whole suite.

## Keyboard and mouse

Everything works with either. `?` (or "Shortcuts" in the status bar) shows this list in the explorer itself.

| Key | Mouse | Action |
| --- | --- | --- |
| `↑` `↓` or `J` `K` | click | Move between suites, groups and tests |
| `←` `→` | click the arrow, or double-click the row | Collapse, or go to the parent; expand, or go to the first child |
| `Home` `End` `PgUp` `PgDn` | | First, last, a page up or down |
| `Enter` | play button on the row | Run the selection: a test, a whole suite or group, or the marked tests |
| `Space` | Ctrl-click, Shift-click | Mark or unmark tests |
| `E` | double-click a test | Open the test in your editor |
| `Shift` `F10`, or the menu key | right-click | Actions for the selection: run, rerun failed, open in editor, mark, copy name or ID, expand or collapse |
| `R` | Run all | Run all tests |
| `F` | Rerun failed | Rerun the failed tests |
| `L` `V` `N` `P` | Run menu | Repeat the last run; run the tests in view, the ones that did not run, the passed ones |
| `M` | Run menu | Open the Run menu (`↑` `↓` to move, `Enter` to run, `Esc` to close) |
| `/` | click the filter | Filter tests; `↓` goes back to the list |
| `Esc` | click elsewhere | Close menus; otherwise clear the filter and the marks |

Shortcuts are ignored while you type in the filter and while Ctrl, Cmd or Alt is held, so browser shortcuts keep working. The list is an accessible tree for screen readers, and the selection is announced as you move.

## Security model

A local web server that can start processes deserves a short explanation of why it is safe to leave running:

- **Loopback only.** The server binds to `127.0.0.1`. Other machines on your network cannot reach it.
- **Per-session token.** Starting a run requires a random token that the server generates when it starts and embeds in the page it serves. A malicious website open in another tab cannot read that page (the browser's same-origin policy prevents it), so it cannot start runs.
- **Host check.** Every request must name `127.0.0.1:<port>` or `localhost:<port>` in its `Host` header, otherwise the server answers `403`. This blocks DNS rebinding, where an attacker's domain suddenly resolves to your machine.
- **Fixed commands.** The page can only ask for "run these test ids" or "run the failed tests". It cannot pass arbitrary arguments, and the ids go through a file rather than the command line.
- **Idle timeout.** The server stops after 30 minutes without a request (never during a run). An open tab polls the server, so in practice it shuts down about half an hour after you close the tab. The next interactive run starts it again.

The coverage report link (shown when the run wrote an [HTML coverage report](coverage.md#reports)) serves files from that report's directory and nothing outside of it.

## Related pages

- [Output](output.md) covers the other places results can go, including a static HTML report for CI artifacts.
- [CLI](cli.md) lists `--explorer`, `--no-explorer`, `--failed` and the `explorer` command.
- [Configuration](configuration.md) has every option mentioned here.
- [Troubleshooting](troubleshooting.md#the-test-explorer-does-not-open) helps when the explorer refuses to start.
