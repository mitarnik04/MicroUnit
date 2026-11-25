---
layout: default
title: MicroUnit
description: A new way to test PHP. Built for today. The dependency-free testing framework, with tests you can read at a glance and fast runs.
---

# MicroUnit

**A new way to test PHP. Built for today.** MicroUnit is a PHP testing framework with zero dependencies. A test is a closure with a name that
says what it checks, the assertions read like sentences, and runs are fast enough that you never
wait for them. Everything else you need is in the box.

```php
<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Cart');

$tester->fixture('cart', fn() => new Cart());

$tester->define('adds_items_and_totals_them', function (Cart $cart): void {
    $cart->add(new Item('Keyboard', 4900));
    $cart->add(new Item('Mouse', 1900));

    Assert::countEquals(2, $cart->items());
    Assert::exact(6800, $cart->total());
});
```

Assertions come in two styles that mix freely: static calls like the ones above, or
[fluent chains](fluent-assertions.md) that start from the value, such as
`AssertArray::begin($cart->items())->notEmpty()->countEquals(2)`.

```bash
composer require --dev microunit/microunit
vendor/bin/microunit init
vendor/bin/microunit
```

[Get started in five minutes](getting-started.md), or bring your PHPUnit suite along with
[one command](migrating-from-phpunit.md).

Featured on PHP News: [“MicroUnit 1.0: A Zero-Dependency Testing Framework for PHP”](https://phpnews.net/microunit) by Eric L. Barnes.

## What you get

<div class="feature-grid">
  <a href="{{ '/assertions/' | relative_url }}"><strong>Assertions</strong><span>Sixty of them, static or fluent, including the ones you keep writing by hand: partial matches, any-order comparisons, soft assertions, eventually.</span></a>
  <a href="{{ '/snapshots/' | relative_url }}"><strong>Snapshots</strong><span>Compare output with a reviewed file. Never created by accident in CI.</span></a>
  <a href="{{ '/mocking/' | relative_url }}"><strong>Mocking</strong><span>Mocks, stubs and spies without a second library, with per-instance call logs.</span></a>
  <a href="{{ '/integration-testing/' | relative_url }}"><strong>Integration testing</strong><span>Fixtures with cleanup, a database helper, an HTTP server and client.</span></a>
  <a href="{{ '/frameworks/' | relative_url }}"><strong>Laravel, Symfony, CodeIgniter</strong><span>Your framework's own testing helpers, inside readable tests.</span></a>
  <a href="{{ '/test-explorer/' | relative_url }}"><strong>Test Explorer</strong><span>A live view of your suite in the browser. Run one test, a suite or the failures.</span></a>
  <a href="{{ '/parallel/' | relative_url }}"><strong>Parallel runs</strong><span>Built in, with no extra package. Suites stay together in one worker.</span></a>
  <a href="{{ '/coverage/' | relative_url }}"><strong>Code coverage</strong><span>In pure PHP: no Xdebug, no PCOV. Text, HTML, Clover and Cobertura.</span></a>
  <a href="{{ '/ai-agents/' | relative_url }}"><strong>AI coding agents</strong><span>Token-cheap output, and a check that catches tests weakened to pass.</span></a>
  <a href="{{ '/migrating-from-phpunit/' | relative_url }}"><strong>Migrating from PHPUnit</strong><span>One command translates your suite, mocks and data providers included.</span></a>
  <a href="{{ '/output/' | relative_url }}"><strong>Reports</strong><span>JUnit, HTML, GitHub Actions annotations, logs, JSON. Several at once.</span></a>
  <a href="{{ '/benchmarks/' | relative_url }}"><strong>Benchmarks</strong><span>Real suites of popular projects, every number reproducible.</span></a>
</div>

## The ideas behind it

**Easy to write, easy to scan.** Tests are read far more often than they are written, by you, by
your colleagues and by AI agents. MicroUnit keeps them plain: a name, a closure, assertions with the
expected value first. No base classes to inherit, no annotations to remember.

**Zero dependencies.** MicroUnit does not install a single Composer package, not even for its own
development. Nothing to conflict with your project, nothing to audit, nothing that breaks when you
upgrade something else.

**Fast, because you run tests all the time.** Developers and agents re-run a file after every small
change. MicroUnit keeps the cost of each run small, and the [benchmarks](benchmarks.md) show it on
real projects.

**Works without setup, configurable in plain PHP.** Install it, write a test, run it: no config file
needed. When your project needs its own setup, one `microunit.config.php` holds a lot of options,
with autocompletion and comments. Command-line flags only change a single run. See
[configuration](configuration.md).

**Built with AI, checked at every step.** I write MicroUnit with AI and review what it writes, and
every change has to pass its own tests on three operating systems, static analysis and a coverage
floor. [Ensuring high quality with AI](quality-with-ai.md) walks through the whole process.

## Sponsors

MicroUnit is free and independent. If it saves you time, consider
[sponsoring it on GitHub](https://github.com/sponsors/mitarnik04) or
[buying the maintainer a coffee](https://coff.ee/mitarnikolic). Sponsors are listed here.

*Your name could be the first one.*
