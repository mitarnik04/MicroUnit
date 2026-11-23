---
layout: default
title: Snapshots
description: Compare output with a reviewed copy stored next to the test, update it in one command, and keep CI honest.
---

# Snapshots

A snapshot test compares a value with a copy of it stored in a file. The first run writes the file, you review it and commit it, and from then on the test fails whenever the value changes.

That is the right tool for big outputs you would never type out by hand: rendered HTML, generated SQL, an API payload, a CLI report, an exported config. MicroUnit has it built in as one assertion, `Assert::matchesSnapshot()`, with no plugin and no extra setup. This page covers where the files go, how the first run, CI and updates behave, and when you should not use snapshots at all.

## The assertion

```php
Assert::matchesSnapshot(mixed $actual, ?string $name = null, string $message = ''): void
```

```php
use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Invoice');

$tester->define('renders_the_invoice', function () {
    $invoice = new Invoice(number: 'INV-1', lines: [['Coffee', 3, 2.5]]);

    Assert::matchesSnapshot($invoice->renderText());
});
```

It only works inside a running test, because the test's name and file decide where the snapshot lives.

## Where snapshots live

Next to the test file, in a `__snapshots__` folder, one subfolder per test file and one `.snap` file per snapshot:

```text
tests/
├── invoice-tests.php
└── __snapshots__/
    └── invoice-tests/
        └── renders_the_invoice.snap
```

The file is named after the test. Characters other than letters, digits, `.`, `_` and `-` become `_`, so a data set test named `greets_ada lovelace` is stored as `greets_ada_lovelace.snap`.

Keeping snapshots beside the tests means they move when the test moves, show up in the same pull request as the code that changed them, and are easy to find when one fails.

## The life of a snapshot

**First run: it is written.** When no snapshot exists, `matchesSnapshot()` writes one and the test passes. Open the file, check that it holds what you expect, and commit it. That review is the actual assertion, so do not skip it.

**Later runs: it is compared.** If the value matches the file, the test passes. If not, the test fails with a line diff against the stored copy:

```text
[✖] renders_the_invoice (3.70ms)
    Failed asserting that the value matches the snapshot C:\project\tests\__snapshots__\invoice-tests\renders_the_invoice.snap (run with --update-snapshots if the change is intended).
    --- Expected
    +++ Actual
      Invoice INV-1
    - Total: 7.50
    + Total: 7.80
```

**Intended change: update it.** When the new output is correct, rewrite the snapshots instead of editing the files by hand:

```bash
vendor/bin/microunit --update-snapshots
```

This rewrites the snapshots of every test that runs, so narrow it down when you only meant to change one:

```bash
vendor/bin/microunit --update-snapshots --filter=Invoice::renders_the_invoice
```

Then look at `git diff`: it shows exactly what changed, and that diff is what you are signing off on.

`--update-snapshots` works with `--parallel` as well; the setting is passed on to the worker processes.

## CI never writes snapshots

A snapshot that is created on the CI server is never reviewed by anyone. It would pass forever, testing nothing. So when MicroUnit detects CI, through a non-empty `CI` environment variable (GitHub Actions, GitLab CI and most others set it) or `TF_BUILD` (Azure Pipelines), a missing snapshot is a failure:

```text
Failed asserting that the snapshot matches: there is no snapshot at /builds/app/tests/__snapshots__/invoice-tests/renders_the_invoice.snap (they are never created in CI; run the tests locally and commit the file).
```

This usually means someone added a snapshot test and forgot to commit the `.snap` file.

Changed snapshots fail in CI exactly as they do locally. An explicit `--update-snapshots` is still obeyed in CI, so do not put it in your pipeline. See [CI/CD](ci-cd.md) for the rest of the pipeline setup.

## Several snapshots in one test

Call `matchesSnapshot()` as often as you like. Without a name, the snapshots are numbered in the order they are taken: the first gets the test's name, the following ones `_2`, `_3` and so on.

```php
$tester->define('renders_both_formats', function () {
    Assert::matchesSnapshot($invoice->renderText()); // renders_both_formats.snap
    Assert::matchesSnapshot($invoice->renderHtml()); // renders_both_formats_2.snap
});
```

Numbering depends on order, so inserting a snapshot in the middle shifts the ones after it. Give them names when a test takes more than two or three, or when you expect to add more:

```php
Assert::matchesSnapshot($invoice->renderText(), name: 'invoice_text');
Assert::matchesSnapshot($invoice->renderHtml(), name: 'invoice_html');
```

A name is added to the test's name (`renders_invoice_text.snap` for a test called `renders`), so it only has to be unique within the test. Named snapshots do not take a number, so adding or removing a named snapshot never renames the unnamed ones around it.

## What gets stored

**Strings are stored verbatim.** The `.snap` file contains exactly the string, so HTML snapshots read like HTML and diffs in your pull request are readable.

**Everything else is exported** in the same format MicroUnit uses for failure messages, with types:

```php
Assert::matchesSnapshot([
    'id' => 1,
    'tags' => ['a', 'b'],
    'when' => new DateTimeImmutable('2026-01-01 00:00 UTC'),
    'meta' => (object) ['ratio' => 1.0],
]);
```

```text
[
    'id' => 1 (int),
    'tags' => [
        0 => 'a' (string),
        1 => 'b' (string),
    ],
    'when' => DateTimeImmutable(2026-01-01T00:00:00.000000+00:00),
    'meta' => stdClass {
        ratio: 1.0 (float),
    },
]
```

Types are part of the snapshot, so `1` turning into `'1'` fails the test. If you want to snapshot JSON, pass the JSON string itself (pretty-printed with `JSON_PRETTY_PRINT`, so diffs are line by line) rather than the decoded array.

**Line endings are normalised.** `\r\n` becomes `\n` on both sides before comparing and when writing, so a snapshot recorded on Windows passes on Linux and the other way round. You do not need a `.gitattributes` rule for `.snap` files.

## When to use snapshots, and when not

Snapshots are great for:

- rendered output: HTML, emails, PDFs as text, Markdown, CLI tables
- generated code and queries: SQL from a query builder, migrations, config files
- large structures where every field matters: serialised API responses, exported reports
- characterisation tests: pinning down what legacy code does today before you refactor it

They are a poor fit when:

- **the expectation is small.** `Assert::exact('INV-1', $invoice->number)` says what matters right in the test. A snapshot hides it in another file.
- **the output is not deterministic.** Timestamps, random ids, absolute paths and unordered hash maps make the snapshot fail on every run. Replace them before snapshotting (a fixed clock, a seeded generator, `str_replace` on the temp path), or use [`Assert::matchesFormat()`](assertions.md#matchesformat) with placeholders like `%d` and `%s` instead.
- **only one detail matters.** If the test is about the total, assert the total. A snapshot of the whole invoice fails for every unrelated wording change, and people learn to run `--update-snapshots` without looking. At that point the test checks nothing.
- **you would not review it.** A 3000-line snapshot nobody reads is not a test, it is a recording.

My rule: if I cannot tell from the diff in a pull request whether the change is right, the snapshot is too big or too noisy.

## Reviewing snapshots in pull requests

Snapshot files are code. Treat their diffs that way:

- **A changed `.snap` file needs a reason in the same pull request.** A code change that explains it, or a sentence in the description. "Updated snapshots" is not a reason.
- **New `.snap` files are reviewed like new assertions**, because that is what they are. Read them.
- **Watch for snapshots that changed without a matching code change.** That usually means non-deterministic output, or someone ran `--update-snapshots` on everything to make a failure go away.
- **Delete orphans.** Renaming or removing a test leaves its old `.snap` file behind; MicroUnit does not clean those up. Remove them in the same pull request.

When an AI agent works on your code, the same applies with more force: an agent that "fixes" a failing snapshot test by updating the snapshot has not fixed anything. [AI coding agents](ai-agents.md) covers how MicroUnit helps you spot weakened tests.
