---
layout: default
title: Ensuring high quality with AI
description: MicroUnit is written with AI. Every change runs through its own test suite twelve times, static analysis, a coverage floor and an integrity check, and every release through the test suites of six real projects.
---

# Ensuring high quality with AI

MicroUnit is written with AI. The code, the tests and the documentation you are reading all start as AI output, and I chose that deliberately. It comes with an obligation, though: if a tool writes the code, I have to be able to show what keeps a bad change from reaching you.

That is what this page does. It follows a change from the first line to a release, and every step links to the place where you can check it yourself.

<figure class="build-path" aria-label="The path of a change, from writing it to a release">
  <ol>
    <li>
      <p><strong>Written</strong> <span>Decided, written and reviewed</span></p>
      <ul>
        <li class="by-me">I decide what it does</li>
        <li>AI writes the code</li>
        <li class="by-me">I review the change</li>
      </ul>
    </li>
    <li>
      <p><strong>Checked on every change</strong> <span>Every push to <code>main</code> and every pull request</span></p>
      <ul>
        <li>Canary</li>
        <li>12 test runs</li>
        <li>Lint and docs</li>
        <li>PHPStan</li>
        <li>Coverage floor</li>
        <li>Integrity check</li>
      </ul>
    </li>
    <li>
      <p><strong>Before a release</strong> <span>The test suites of six real open-source projects</span></p>
      <ul>
        <li>Six real projects</li>
      </ul>
    </li>
  </ol>
  <figcaption><span class="build-key">Runs without me</span><span class="build-key by-me">Done by me</span></figcaption>
</figure>

## Who decides, who writes

I'm not handing MicroUnit over to AI. I'm one developer with a very fast tool, and the work splits along what each of us is good at. AI is good at implementing a change that is clearly specified. It is much less reliable at deciding what the change should be, which API will read well in a test, or when the right answer is no.

<div class="build-roles">
  <div class="build-role by-me">
    <span class="build-role-label">Me</span>
    <ul>
      <li>What MicroUnit does, and what it leaves out</li>
      <li>How every public API reads in a test</li>
      <li>The architecture and the rules in <a href="https://github.com/mitarnik04/MicroUnit/blob/main/CONTRIBUTING.md#code-style">CONTRIBUTING.md</a></li>
      <li>Reviewing what AI writes, and deciding when it is ready</li>
    </ul>
  </div>
  <div class="build-role">
    <span class="build-role-label">AI</span>
    <ul>
      <li>Writing the code and the tests for those decisions</li>
      <li>Writing the docs straight from the source</li>
      <li>Following the same rules, compact in <a href="https://github.com/mitarnik04/MicroUnit/blob/main/CLAUDE.md">CLAUDE.md</a></li>
    </ul>
  </div>
</div>

My review is the one step on this page you can't verify from the outside, and it covers what no automated check can: whether a change is the right one, whether an API reads well in a test, and whether a test checks the right thing in the first place. Everything that can be checked automatically is, on every change. That leaves my attention for the questions only a person can answer, and I look at every change.

## The harness the AI works in

Every AI session on MicroUnit starts in the same harness. Its job is to make the right thing the easy thing, long before a change reaches my review.

- **The rules load first.** [`CLAUDE.md`](https://github.com/mitarnik04/MicroUnit/blob/main/CLAUDE.md) is read at the start of every session: the code rules (zero dependencies, typed, `final readonly` by default, no dead code), the commands, the known pitfalls, and what "done" means. Done is tests added, the suite, canary, lint and PHPStan green, and the docs updated in the same change. [`PRODUCT.md`](https://github.com/mitarnik04/MicroUnit/blob/main/PRODUCT.md) adds who MicroUnit is for and how it talks, so the docs keep one voice.
- **MicroUnit answers the agent in its own format.** In an agent session the suite replies with one compact JSON object ([AI coding agents](ai-agents.md) describes it): only the failures, each with where it failed, the expected and actual values and the code around it, plus a warning for every test that passes without asserting anything. The agent's attention goes to what broke.

## Twelve runs for every change

Every push to `main` and every pull request runs the full suite twelve times: on Ubuntu, Windows and macOS, on PHP 8.5 and 8.6, once sequentially and once spread over four parallel workers.

<figure class="build-matrix" aria-label="The test matrix: three operating systems, two PHP versions, sequential and parallel">
  <div class="build-matrix-grid">
    <span></span><span class="build-matrix-head">PHP 8.5</span><span class="build-matrix-head">PHP 8.6</span>
    <span class="build-matrix-os">Ubuntu</span><span class="build-matrix-cell"><i>seq</i><i>par</i></span><span class="build-matrix-cell"><i>seq</i><i>par</i></span>
    <span class="build-matrix-os">Windows</span><span class="build-matrix-cell"><i>seq</i><i>par</i></span><span class="build-matrix-cell"><i>seq</i><i>par</i></span>
    <span class="build-matrix-os">macOS</span><span class="build-matrix-cell"><i>seq</i><i>par</i></span><span class="build-matrix-cell"><i>seq</i><i>par</i></span>
  </div>
  <figcaption>3 operating systems × 2 PHP versions × sequential and parallel</figcaption>
</figure>

Two settings make these runs stricter than they need to be, on purpose. `error_reporting=-1` means every notice and deprecation gets reported instead of passing as harmless, and OPcache is off, so every run compiles the code from scratch. Running sequentially and in parallel catches a kind of bug that only shows up in one of the two: a test that quietly depends on what another test left behind. The whole setup is one file, [`.github/workflows/ci.yml`](https://github.com/mitarnik04/MicroUnit/blob/main/.github/workflows/ci.yml), and every run is public in the [Actions history](https://github.com/mitarnik04/MicroUnit/actions/workflows/ci.yml).

## MicroUnit is tested with MicroUnit

The suite in `tests/` runs on MicroUnit itself. I chose that deliberately, because it turns the suite into a loop:

<figure class="build-loop" aria-label="The self-testing loop">
  <div class="build-loop-ring">
    <ol>
      <li><strong>A change</strong><span>to MicroUnit</span></li>
      <li><strong>Its own suite runs</strong><span>with the code that just changed</span></li>
      <li><strong>A test turns red</strong><span>or an exception surfaces</span></li>
      <li><strong>The fix, plus a test</strong><span>that stays in the suite for good</span></li>
    </ol>
    <p class="build-loop-center">Stricter with every bug</p>
  </div>
  <div class="build-canary"><strong>Canary</strong><span>Runs first, outside the loop, and checks that failures still get reported at all.</span></div>
</figure>

Every feature runs every day against a real suite, so a regression usually shows up here first. Every fixed bug leaves a test behind, so that bug can't come back unnoticed. The parallel runner, the coverage tool and the integrity check all run on MicroUnit's own code in every CI run.

A fair question is whether a broken MicroUnit could pass its own tests. In practice that is hard to arrange, because MicroUnit is both the instrument and the thing being measured. The suite doesn't only check that assertions pass: dozens of tests check that they *fail* when they should. If `Assert::exact()` broke and let everything through, the tests that expect it to throw on a mismatch are checked by `Assert::throws()`, a different piece of code, and they go red. Integration tests go one step further and run the real command line as a separate process, against projects whose outcome is known.

That leaves one case a loop can't judge about itself: whether failures get reported at all. That is the canary's job. Before the suite runs, [`bin/canary.php`](https://github.com/mitarnik04/MicroUnit/blob/main/bin/canary.php) runs MicroUnit on four tests with known outcomes: one passes, one has a failing assertion, one expects an exception that never comes, and one throws. The script loads none of MicroUnit's code and checks the exit code and the report with plain PHP. If MicroUnit ever stops telling a red test from a green one, the canary turns red before anything else does.

## Checks that run without me

<dl class="build-list">
  <div><dt><a href="https://github.com/mitarnik04/MicroUnit/blob/main/bin/canary.php">Canary</a></dt><dd>Four tests with known outcomes, checked without MicroUnit's code before the suite runs.</dd></div>
  <div><dt><a href="https://github.com/mitarnik04/MicroUnit/blob/main/phpstan.neon.dist">Static analysis</a></dt><dd>PHPStan at its strictest level, max, on all of <code>src/</code> and <code>bin/</code>, with no baseline file. It runs as a PHAR, never as a dependency.</dd></div>
  <div><dt><a href="https://github.com/mitarnik04/MicroUnit/blob/main/microunit.config.php">Coverage floor</a></dt><dd>Every CI run measures MicroUnit's coverage with its own coverage tool. About half of the lines in <code>src/</code> are covered today, and the run fails below 47%, so coverage can't quietly slide. The number is lower than the testing behind it, because here the coverage tool measures the framework it is part of. The files that start a run (the coverage tool itself, the command line, the configuration) are already running before measuring begins, so they show 0% even though they have tests. Your own project loads its code after measuring starts, so this doesn't happen there. MicroUnit's integration tests also start a second MicroUnit as its own process, to test the command line exactly as you use it, and the six real projects run outside the measurement as well.</dd></div>
  <div><dt><a href="https://github.com/mitarnik04/MicroUnit/blob/main/.github/workflows/ci.yml">Integrity check</a></dt><dd>Compares the tests with the previous version and lists removed assertions, changed expected values, new skips and removed tests.</dd></div>
  <div><dt><a href="https://github.com/mitarnik04/MicroUnit/blob/main/bin/lint.php">Lint</a></dt><dd>Valid PHP 8.5 syntax, one class per file, namespaces that match folders, and the docs checks described below.</dd></div>
  <div><dt><a href="https://github.com/mitarnik04/MicroUnit/blob/main/.php-cs-fixer.dist.php">Formatting</a></dt><dd>PHP-CS-Fixer with PSR-12 and a few house rules, also as a PHAR.</dd></div>
</dl>

If I had to point to one of these first, it would be the integrity check, because it answers the specific risk that comes with AI. When a test goes red, an agent can make it green the wrong way: drop the assertion, change the expected value to whatever the code returns now, add a skip. The integrity check lists exactly those changes. On a pull request, any finding fails the build. On my own pushes it lists them in the run, so a deliberate change, like renaming a test, stays visible without blocking anything. In an agent session it also runs after every test run, so the agent hears about a weakened test while it is still working, long before the change reaches me. It is the same check any project can turn on with `withIntegrityCheck()`.

## Documentation written from the source

The documentation and the README are written with AI too, from the source and in the same commit as the code they describe, so the two change together.

As a safety net, CI checks what can be checked mechanically: every CLI flag, config option, assertion and fluent method has to appear in the docs, and every link between pages, from the sidebar and from the README has to lead somewhere. Whether each sentence says the right thing is part of my review. If you find one that doesn't, [tell me](https://github.com/mitarnik04/MicroUnit/issues): a wrong sentence in the docs is a bug like any other.

## Real projects before every release

My own suite knows MicroUnit well. That is also its limit: it only contains the cases I thought of. So before a release, MicroUnit runs the test suites of six real open-source projects, each pinned to an exact commit:

<dl class="build-list build-list-projects">
  <div><dt><code>brick/math</code></dt><dd>About 25,000 data-driven tests</dd></div>
  <div><dt><code>nesbot/carbon</code></dt><dd>1,000+ test classes and deep inheritance</dd></div>
  <div><dt><code>webmozart/assert</code></dt><dd>About 5,000 data sets on one test method</dd></div>
  <div><dt><code>league/commonmark</code></dt><dd>File fixtures and spec tests</dd></div>
  <div><dt><code>symfony/yaml</code></dt><dd>A Symfony component suite</dd></div>
  <div><dt><code>monolog/monolog</code></dt><dd>Mocks, handlers and I/O</dd></div>
</dl>

Their tests are translated with `microunit migrate`, and nobody touches the result by hand. A project only counts when every test in the set passes under MicroUnit and MicroUnit runs exactly as many tests as PHPUnit does on the same code. The few tests left out of the set are published, each with its reason. That is the gate a release has to pass, and the [benchmarks](benchmarks.md) are measured on the same verified runs. The projects are public and the commits are pinned, so you can [reproduce it](https://github.com/mitarnik04/MicroUnit/blob/main/benchmarks/README.md#reproducing) on your own machine.

## Check it yourself

| What | Where |
| --- | --- |
| The CI pipeline and every run | [`ci.yml`](https://github.com/mitarnik04/MicroUnit/blob/main/.github/workflows/ci.yml), [Actions history](https://github.com/mitarnik04/MicroUnit/actions/workflows/ci.yml) |
| The canary | [`bin/canary.php`](https://github.com/mitarnik04/MicroUnit/blob/main/bin/canary.php) and its [fixture](https://github.com/mitarnik04/MicroUnit/tree/main/tests/Fixtures/projects/canary) |
| Lint and the docs checks | [`bin/lint.php`](https://github.com/mitarnik04/MicroUnit/blob/main/bin/lint.php) |
| Static analysis | [`phpstan.neon.dist`](https://github.com/mitarnik04/MicroUnit/blob/main/phpstan.neon.dist) |
| The test suite and the coverage floor | [`tests/`](https://github.com/mitarnik04/MicroUnit/tree/main/tests), [`microunit.config.php`](https://github.com/mitarnik04/MicroUnit/blob/main/microunit.config.php) |
| The agent harness | [`CLAUDE.md`](https://github.com/mitarnik04/MicroUnit/blob/main/CLAUDE.md), [`PRODUCT.md`](https://github.com/mitarnik04/MicroUnit/blob/main/PRODUCT.md) |
| The real projects and their results | [`benchmarks/`](https://github.com/mitarnik04/MicroUnit/tree/main/benchmarks) |
| The rules every change follows | [`CONTRIBUTING.md`](https://github.com/mitarnik04/MicroUnit/blob/main/CONTRIBUTING.md) |
