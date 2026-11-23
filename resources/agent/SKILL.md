---
name: microunit
description: Write, run and fix PHP tests with MicroUnit (closures + Assert::, zero dependencies). Use whenever the project has microunit.config.php or vendor/bin/microunit, or the user asks for PHP tests with MicroUnit.
---

# Testing with MicroUnit

MicroUnit tests are plain closures in files matching the config's patterns (default `*-tests.php`,
often `*Test.php`). Read `microunit.config.php` first: it names the test directories, groups,
bootstrap file and framework integration.

## Run

```bash
vendor/bin/microunit                      # all tests; in agent sessions the output is compact JSON
vendor/bin/microunit tests/CartTest.php   # one file
vendor/bin/microunit --filter=Cart::adds  # by id ("Suite::test"), substring or /regex/
vendor/bin/microunit --failed             # only what failed last time
vendor/bin/microunit --list=json          # discover tests without running them
```

Exit code 0 = pass, 1 = failures, 2 = usage error.
The JSON lists only failures: `at` (file:line), `expected`/`actual` or `diff`, `code`, `trace`.
`weakenedTests` (when the project enabled the integrity check) means you removed or weakened tests compared to the last commit: undo that and fix
the code instead, or tell the user why the test had to change.

## Write

```php
<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Cart');            // suite = the unit under test

$tester->fixture('cart', fn() => new Cart());      // fresh per test, injected by parameter name

$tester->define('adds_items', function (Cart $cart): void {
    $cart->add(new Item('book', 1200));

    Assert::countEquals(1, $cart->items());
    Assert::exact(1200, $cart->total());           // expected first, actual second
});

$tester->defineGroup('applies_discounts', function (int $percent, int $total): void {
    Assert::exact($total, new Cart(10000)->withDiscount($percent)->total());
}, [
    'none' => [0, 10000],
    'a quarter' => [25, 7500],
]);
```

Rules:
- Test names are `snake_case` sentences describing behaviour. One suite per file.
- Argument order is PHPUnit's: expected first, actual last, optional message last.
- `exact()` compares with `===`, `equals()` with `==`. Prefer `exact()`.
- Checks on a value also come fluent: `AssertString::begin($slug)->startsWith('post-')->matchesRegex('/^[a-z0-9-]+$/')`
  (`AssertSingle`, `AssertNumeric`, `AssertArray`, `AssertString`, `AssertFile`). Write the style the project's tests already use.
- Use `Assert::throws(fn () => ..., Type::class, 'message part')` for exceptions; it returns the
  exception for further checks.
- Shared state goes in fixtures (`shared: true` for one per suite), never in globals or `static`.
  `setUp`/`tearDown` can take fixtures by parameter name too.
- Helpers are plain functions in the test file; for files that share helpers use a namespace.

Assertions worth knowing: `exact`, `equals`, `isTrue`, `isNull`, `instanceOf`, `isType('int|string')`,
`countEquals`, `contains`, `containsAll`, `hasKey`, `containsSubset` (partial array/JSON match),
`equivalent` (any order), `each($items, fn ($item) => ...)`, `isSorted`, `isUnique`, `stringContains`,
`matchesRegex`, `matchesFormat('%d items')`, `jsonEquals`, `fileExists`, `fileContentEquals`,
`equalsWithDelta`, `dateEquals(..., toleranceInSeconds: 5)`, `doesNotThrow`, `triggersError`,
`prints`, `eventually(fn () => ..., timeoutInSeconds: 3)`, `multiple(fn () => ...)` (report all
failures), `matchesSnapshot($value)`, `skip`/`skipIf`.

## Mocks

```php
use MicroUnit\Assertion\AssertMock;
use MicroUnit\Mock\MicroMock;
use MicroUnit\Mock\MockBuilder;

$mailer = MockBuilder::create(Mailer::class)->returns('send', true)->build()->newInstance();
MicroMock::of($mailer)->returns('send', false);      // change it later, e.g. in one test
// ... exercise the code ...
AssertMock::begin($mailer)->isCalledOnce('send')->isCalledWith('send', ['ada@example.com']);
```

## Frameworks

With `->withFramework(new LaravelIntegration())` (Symfony, CodeIgniter likewise) every test runs
inside the framework's test case; its helpers are static calls on the proxy:
`Laravel::actingAs($user)->postJson('/api/orders', $data)->assertCreated();`,
`Laravel::assertDatabaseHas('orders', [...])`, `Laravel::uses($tester, RefreshDatabase::class);`.

## Never

- Never weaken a test to make it pass (remove assertions, change expected values, skip it, assert
  `true`). Fix the code, or ask.
- Never edit migrated output in `tests-microunit/` by hand while the PHPUnit originals are still
  the source of truth; re-run `microunit migrate` instead.

Docs: https://mitarnik04.github.io/MicroUnit/
