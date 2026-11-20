> [!IMPORTANT]
> **MicroUnit 1.0 is not released yet.** This README and the
> [documentation](https://mitarnik04.github.io/MicroUnit/) already describe 1.0, a substantial
> rewrite. Until it is out, Packagist only has the 1.0 betas, which lack much of what you read here,
> so the install command below does not work yet. Watch the repository to hear when 1.0 lands.

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/brand/banner-dark.png">
    <img alt="MicroUnit: minimal, modern, mighty PHP testing" src="assets/brand/banner-light.png" width="100%">
  </picture>
</p>

<p align="center">
  <strong>The PHP testing framework with zero dependencies.</strong><br>
  Tests you can read at a glance, and runs fast enough that you never wait for them.
</p>

<p align="center">
  <a href="https://github.com/mitarnik04/MicroUnit/actions/workflows/ci.yml"><img alt="CI" src="https://github.com/mitarnik04/MicroUnit/actions/workflows/ci.yml/badge.svg"></a>
  <a href="https://packagist.org/packages/microunit/microunit"><img alt="Packagist" src="https://img.shields.io/packagist/v/microunit/microunit?label=packagist&color=0A17A8"></a>
  <img alt="PHP 8.5+" src="https://img.shields.io/badge/php-8.5%2B-0A17A8">
  <img alt="Zero dependencies" src="https://img.shields.io/badge/dependencies-0-3FC6F3">
  <a href="LICENSE"><img alt="License: Apache 2.0" src="https://img.shields.io/badge/license-Apache%202.0-0A17A8"></a>
</p>

<p align="center">
  <a href="https://mitarnik04.github.io/MicroUnit/">Documentation</a> ·
  <a href="https://mitarnik04.github.io/MicroUnit/getting-started/">Getting started</a> ·
  <a href="https://mitarnik04.github.io/MicroUnit/migrating-from-phpunit/">Migrating from PHPUnit</a> ·
  <a href="https://mitarnik04.github.io/MicroUnit/benchmarks/">Benchmarks</a>
</p>

---

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

$tester->defineGroup('applies_discounts', function (int $percent, int $total): void {
    Assert::exact($total, new Cart(10000)->withDiscount($percent)->total());
}, [
    'none' => [0, 10000],
    'a quarter' => [25, 7500],
]);
```

```text
$ vendor/bin/microunit

-- Cart --
[✔] adds_items_and_totals_them (2.18ms)
[✔] applies_discounts_none (0.00ms)
[✔] applies_discounts_a quarter (0.00ms)

Summary: 3/3 passed, 0 failed in 14.37ms
```

No base classes, no annotations, no XML. A test is a closure with a name that says what it checks.

## Why MicroUnit

- **Fast where it counts.** On the real test suites of six popular PHP projects, MicroUnit beat PHPUnit
  in all 24 comparisons: up to 5× faster on a full suite, and typically 2.6× faster when you re-run
  a single file, which is what you and your AI agent do all day. Method, raw data and every
  exclusion are published: [benchmarks](https://mitarnik04.github.io/MicroUnit/benchmarks/).
- **Zero dependencies.** Not one Composer package, not even for development. Nothing to conflict
  with, nothing to audit, nothing that breaks when you upgrade something else.
- **Built for AI-assisted development.** Agents get compact JSON with only the failures (a fraction
  of the tokens), and MicroUnit warns when tests were weakened to make them pass.
  [More](https://mitarnik04.github.io/MicroUnit/ai-agents/)
- **Everything in the box.** Mocks, snapshots, data sets, fixtures, code coverage without Xdebug or
  PCOV, parallel runs, a browser Test Explorer, JUnit/HTML/log reports, and Laravel, Symfony and
  CodeIgniter integrations.
- **Configured in PHP.** One `microunit.config.php` with autocompletion instead of XML.
- **Switching takes one command.** `microunit migrate` translates your PHPUnit suite, mocks and data
  providers included, and tells you exactly what it could not.

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/brand/benchmarks/suite-dark.png">
    <img alt="Full test suite: MicroUnit is 1.03 to 5.14 times faster than PHPUnit on the test suites of six popular PHP projects" src="assets/brand/benchmarks/suite-light.png" width="100%">
  </picture>
</p>

## Install

```bash
composer require --dev microunit/microunit
vendor/bin/microunit init
```

`init` writes a config and a first test, set up for Laravel, Symfony or CodeIgniter if your project
uses one. Then run `vendor/bin/microunit`. Requires PHP 8.5 or newer; tested on 8.5 and 8.6.

Coming from PHPUnit? `vendor/bin/microunit migrate` converts your tests into `tests-microunit/` and
leaves the originals alone, so both suites run side by side until you are happy.
[Migration guide](https://mitarnik04.github.io/MicroUnit/migrating-from-phpunit/)

## A quick tour

**Assertions that read like sentences.** Expected value first, actual value second, as in PHPUnit.
Plus the ones you keep writing by hand:

```php
Assert::containsSubset(['user' => ['name' => 'Ada']], $response->json());  // partial match
Assert::equivalent([3, 1, 2], $ids);                                         // any order
Assert::each($orders, fn(Order $order) => Assert::isTrue($order->isPaid()));
Assert::eventually(fn() => Assert::notNull($mailbox->latest()), timeoutInSeconds: 3);
Assert::multiple(function () use ($user) {                                   // report every failure
    Assert::exact('Ada', $user->name);
    Assert::exact('ada@example.com', $user->email);
});
Assert::matchesSnapshot($invoice->render());
```

[All assertions](https://mitarnik04.github.io/MicroUnit/assertions/) ·
[Snapshots](https://mitarnik04.github.io/MicroUnit/snapshots/)

**Mocks without a second library.**

```php
$mailer = MockBuilder::create(Mailer::class)->returns('send', true)->build()->newInstance();

new Checkout($mailer)->complete($order);

AssertMock::begin($mailer)->isCalledOnce('send')->isCalledWith('send', [$order->email]);
```

[Mocking](https://mitarnik04.github.io/MicroUnit/mocking/)

**Your framework, unchanged.**

```php
$tester->define('customers_can_place_orders', function (): void {
    Laravel::actingAs(User::factory()->create())
        ->postJson('/api/orders', ['sku' => 'KB-01'])
        ->assertCreated();

    Laravel::assertDatabaseHas('orders', ['sku' => 'KB-01']);
});
```

[Laravel, Symfony, CodeIgniter](https://mitarnik04.github.io/MicroUnit/frameworks/)

**A Test Explorer in your browser.** `microunit explorer` opens a live view of your suite: run one
test, a suite, only the failures, jump to the line in your editor. Opt-in, so it costs nothing
when you don't use it.

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/brand/explorer-dark.png">
    <img alt="The MicroUnit Test Explorer showing a failed assertion with its diff" src="assets/brand/explorer.png" width="100%">
  </picture>
</p>

[Test Explorer](https://mitarnik04.github.io/MicroUnit/test-explorer/)

**And the rest:** [parallel runs](https://mitarnik04.github.io/MicroUnit/parallel/) with
`--parallel`, [code coverage](https://mitarnik04.github.io/MicroUnit/coverage/) in pure PHP,
[integration testing](https://mitarnik04.github.io/MicroUnit/integration-testing/) with a database,
HTTP server and HTTP client in the toolkit, [reports](https://mitarnik04.github.io/MicroUnit/output/)
for JUnit, HTML, GitHub Actions and logs, and a [CLI](https://mitarnik04.github.io/MicroUnit/cli/)
where `microunit help <command>` explains everything.

## Built for AI coding agents

AI agents run tests constantly, often after every small change. Three things make MicroUnit a better
partner for that loop:

- **Speed.** Less waiting per run adds up across hundreds of runs a day.
- **Token-cheap output.** In an agent session (Claude Code is detected automatically, others set
  `MICROUNIT_AGENT=1`) the output is one compact JSON object with only the failures: where, expected,
  actual, the code around it.
- **Protection against test cheating.** Agents sometimes "fix" a red test by deleting an assertion or
  changing the expected value. Turn on the integrity check and MicroUnit compares the tests with the last
  commit after every run, and tells the agent, and you, when a test got weaker. `microunit integrity origin/main` does the same in code review.

`vendor/bin/microunit init --skill` installs a skill that teaches your agent how to write MicroUnit
tests. [More](https://mitarnik04.github.io/MicroUnit/ai-agents/)

## The story

I started MicroUnit as a side project in May 2025, because I wanted PHP tests that were quick to
write and quick to read. It got to a public beta that summer, and then it stalled: building coverage, parallel
runs, framework integrations and a proper explorer next to a day job was more than one person could
do. AI changed that. With it as my tool, I finally had the capacity to bring MicroUnit to its first
official release: to test every part of it properly, all the way down to running the real test suites
of the projects in the benchmark, and to build every feature I had imagined for it. MicroUnit is
made for the way software gets written now, so it is fitting that it was made that way too.

## Contributing

Issues, ideas and pull requests are very welcome. [CONTRIBUTING.md](CONTRIBUTING.md) explains the
setup (five minutes), the code standards and what makes a pull request easy to merge.
Security issues: please follow [SECURITY.md](SECURITY.md).

## Support the project

If MicroUnit saves you time, consider sponsoring it. It keeps the project independent and moving.

[![Sponsor](https://img.shields.io/github/sponsors/mitarnik04?label=Sponsor&logo=GitHub)](https://github.com/sponsors/mitarnik04)
[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-orange?logo=buy-me-a-coffee&logoColor=white)](https://coff.ee/mitarnikolic)

Every sponsor is listed on the [documentation site](https://mitarnik04.github.io/MicroUnit/).

## License

Apache 2.0, see [LICENSE](LICENSE).

<p align="center"><strong>Minimal. Modern. Mighty.</strong></p>
