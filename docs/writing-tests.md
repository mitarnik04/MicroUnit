---
layout: default
title: Writing tests
description: Suites, tests, data sets, fixtures, setUp and tearDown, skipping, output and test ids.
---

# Writing tests

A MicroUnit test is a closure with a name. That is the whole model, and everything on this page builds on it. Suites group tests. Data sets run one closure with different inputs. Fixtures hand each test the objects it needs, and lifecycle hooks cover the rest.

The goal is a test file you can read from top to bottom like a description of what the code does, whether a colleague wrote it, you wrote it at 2am, or an AI agent wrote it.

## Test files and suites

A test file asks for the tester of a suite and defines tests on it:

```php
<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Cart');

$tester->define('starts_empty', function (): void {
    Assert::isTrue(new Cart()->isEmpty());
});
```

The suite name is the unit under test: `Cart`, `Mailer`, `InvoiceRepository`. It shows up as the section header in the output and as the first half of every test id.

The convention is **one suite per file**, with the file named after the suite (`tests/Cart/cart-tests.php`, or `tests/CartTest.php` if you configure `*Test.php` as the pattern). MicroUnit does not enforce it, but a reader should never have to grep to find where a suite's tests live.

`getTester()` returns the same `Tester` for the same name, so two files that ask for `'Cart'` add to the same suite. That works, but hooks and fixtures belong to the suite, not the file: if both files call `setUp()`, the second one silently replaces the first. Another reason to stick to one file per suite.

> Always use `TestSetup::getTester('Suite')`. A `Tester` created with `new Tester(...)` is not registered anywhere, so its tests never run.

Test files are plain PHP scripts that MicroUnit includes during discovery. Defining a test only registers it; nothing runs until every file has been loaded.

## Defining a test

```php
$tester->define('applies_the_discount_to_the_total', function (): void {
    $cart = new Cart();
    $cart->add('book', price: 2000);

    $cart->applyDiscount(percent: 10);

    Assert::exact(1800, $cart->total());
});
```

`define(string $name, callable $test, mixed ...$args)` takes the name, the test, and optionally arguments that are passed to the closure:

```php
$tester->define('adds_two_numbers', function (int $a, int $b): void {
    Assert::exact(3, $a + $b);
}, 1, 2);
```

You rarely need `...$args`. Data sets and fixtures (below) say the same thing more clearly.

### Name tests after behaviour

Test names are `snake_case` sentences that describe what the code does, not which method you called:

| Instead of | Write |
| --- | --- |
| `testTotal` | `sums_the_line_totals` |
| `test_add_invalid` | `rejects_a_negative_quantity` |
| `discount2` | `caps_the_discount_at_the_item_price` |

Read the output out loud: "Cart: rejects a negative quantity". If that sentence is wrong, the test is wrong or badly named. This also helps the Test Explorer, `--filter` and AI agents, which all work with these names.

Names are free text, so MicroUnit will not stop you from using spaces or camelCase. If you reuse a name inside a suite, the second test still runs and gets a unique id with `#2` appended. Treat that as a hint that one of them needs a better name.

## Data sets: `defineGroup()`

When the same behaviour should hold for several inputs, define the test once and give it data sets:

```php
$tester->defineGroup('applies_quantity', function (int $price, int $quantity, int $total): void {
    $cart = new Cart();
    $cart->add('sku', $price, $quantity);

    Assert::exact($total, $cart->total());
}, [
    'single item' => [100, 1, 100],
    'three items' => [100, 3, 300],
    'free item' => [0, 5, 0],
]);
```

Each data set becomes its own test named `{baseName}_{key}`, here `applies_quantity_single item`, `applies_quantity_three items` and `applies_quantity_free item`. The key is appended as written, so pick keys that read well in the output. Each array is spread into the closure's parameters in order.

A data set that is not an array is passed as the only argument:

```php
$tester->defineGroup('rejects_blank_input', function (string $input): void {
    Assert::throws(fn() => new Slug($input), InvalidArgumentException::class);
}, [
    'empty' => '',
    'spaces' => '   ',
]);
```

Data sets without a string key are numbered by their position, starting at 1. Name them anyway. `rejects_blank_input_2` tells you nothing when it fails.

### Generators

Any iterable works, so a generator can build the data sets. Useful when the data is computed, read from a file, or too long to inline:

```php
function priceCases(): Generator
{
    yield 'zero' => [0, 0];
    yield 'one cent' => [1, 1];
    yield 'large amount' => [PHP_INT_MAX, PHP_INT_MAX];
}

$tester->defineGroup('keeps_the_price', function (int $price, int $expected): void {
    $cart = new Cart();
    $cart->add('x', $price);

    Assert::exact($expected, $cart->total());
}, priceCases());
```

### `TestCase` objects

Data sets can also be written as `TestCase` objects. They mean exactly the same thing as keyed arrays:

```php
use MicroUnit\Core\TestCase;

$tester->defineGroup('addition', function (int $a, int $b, int $sum): void {
    Assert::exact($sum, $a + $b);
}, [
    new TestCase('zero', [0, 0, 0]),
    new TestCase('positive', [1, 2, 3]),
]);
```

`new TestCase(string $testNameSuffix, mixed $params = [])` names the test `{baseName}_{suffix}`; an array in `$params` is spread, anything else is passed as the only argument. You can mix `TestCase` objects and plain data sets in one list. For new code, the keyed array is shorter and reads better.

## Fixtures

A fixture is a named resource that a test receives by declaring a parameter with that name. You declare it once per suite, and every test that wants it simply asks:

```php
$tester->fixture('cart', fn() => new Cart());

$tester->define('starts_empty', function (Cart $cart): void {
    Assert::isTrue($cart->isEmpty());
});

$tester->define('sums_the_line_totals', function (Cart $cart): void {
    $cart->add('apple', 30, quantity: 2);
    $cart->add('pear', 45);

    Assert::exact(105, $cart->total());
});
```

The matching is by **parameter name**, not by type. `Cart $cart` gets the `cart` fixture; `Cart $basket` would not.

The full signature is:

```php
$tester->fixture(string $name, callable $factory, ?callable $cleanup = null, bool $shared = false);
```

- **`$factory`** creates the value. It runs lazily, the first time a test (or setUp, tearDown or another fixture) asks for it. Tests that do not ask never pay for it.
- **`$cleanup`** receives the value after the test, also when the test failed. A cleanup that throws turns an otherwise passing test into a failure, because leaking state into the next test is a bug too.
- **`$shared: true`** creates one instance for the whole suite instead of one per test, and cleans it up after the suite's last test. Use it for expensive things that tests do not mutate in conflicting ways, like a database connection or an HTTP server. A shared fixture can use other shared fixtures, but not per-test ones. It would keep the first test's instance after that instance was cleaned up, so MicroUnit fails the test with an explanation instead.

Without `shared`, every test gets a fresh instance. That is the default for a reason: tests that share mutable state start depending on their order, and then you have a very bad afternoon.

### Fixtures and data sets together

Fixture parameters and data set values mix freely. Fixtures are matched by name, and the remaining parameters are filled from the data set in order:

```php
$tester->defineGroup('applies_quantity', function (int $price, int $quantity, int $total, Cart $cart): void {
    $cart->add('sku', $price, $quantity);

    Assert::exact($total, $cart->total());
}, [
    'single item' => [100, 1, 100],
    'three items' => [100, 3, 300],
]);
```

The same goes for the extra arguments of `define()`.

### Fixtures that depend on fixtures

A factory can ask for other fixtures by parameter name, exactly like a test:

```php
$tester->fixture('directory', function (): string {
    $directory = sys_get_temp_dir() . '/uploads-' . uniqid();
    mkdir($directory);

    return $directory;
}, cleanup: fn(string $directory) => rmdir($directory));

$tester->fixture('upload', function (string $directory): string {
    $file = "$directory/avatar.png";
    file_put_contents($file, 'fake image');

    return $file;
}, cleanup: fn(string $file) => unlink($file));

$tester->define('stores_the_upload', function (string $upload): void {
    Assert::fileExists($upload);
});
```

Cleanups run in reverse creation order, so `upload` is deleted before `directory` is removed. If two fixtures depend on each other in a circle, the test fails with `Fixtures depend on each other in a circle: a → b → a` instead of recursing until PHP runs out of memory.

## Lifecycle hooks

Fixtures cover most setup needs. For everything else there are four hooks per suite:

| Hook | Runs | Receives |
| --- | --- | --- |
| `setUpOnce(fn)` | once, before the suite's first test | nothing |
| `setUp(fn)` | before every test | `setUpOnce()`'s return value (if not null), plus fixtures by name |
| `tearDown(fn)` | after every test, also after a failure | the `TestResult`, `setUp()`'s return value, plus fixtures by name |
| `tearDownOnce(fn)` | once, after the suite's last test | `setUpOnce()`'s return value |

### `setUp` and `tearDown`

A non-null return value of `setUp()` is passed to every test as its **first** argument:

```php
$tester->setUp(fn(): Cart => new Cart(currency: 'EUR'));

$tester->define('uses_the_configured_currency', function (Cart $cart): void {
    Assert::exact('EUR', $cart->currency);
});
```

With a `setUp()` in place, that first argument comes before any data set values, so a `defineGroup()` closure looks like `function (Cart $cart, int $price, int $total)`. That positional rule is easy to forget, which is one reason named fixtures are usually the better tool: they do not care about order.

`tearDown()` gets the test's result first and the setUp value second:

```php
use MicroUnit\Core\TestResult;

$tester->tearDown(function (TestResult $result, Cart $cart): void {
    $cart->clear();
});
```

### `setUpOnce` and `tearDownOnce`

These run once per suite and are the place for expensive resources: a database connection, a server process, a container.

```php
$tester->setUpOnce(function (): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

    return $pdo;
});

$tester->tearDownOnce(function (PDO $pdo): void {
    // close connections, stop servers, remove files
});
```

The return value of `setUpOnce()` goes to `setUp()`. Without a `setUp()`, it goes straight to every test as the first argument.

If `setUpOnce()` throws, every test of the suite fails with `setUpOnce() of suite '...' failed: ...`. Running them without their shared setup would only produce noise.

If it calls `Assert::skip()`, every test of the suite is skipped instead, which is exactly what you want for integration tests that need a service:

```php
$tester->setUpOnce(function (): PDO {
    Assert::skipIf(getenv('DATABASE_URL') === false, 'Set DATABASE_URL to run the Postgres tests');

    return new PDO(getenv('DATABASE_URL'));
});
```

Anything `setUpOnce()` and `tearDownOnce()` print is discarded so it cannot interleave with the test output. A failure during `tearDownOnce()` (or the cleanup of shared fixtures) is reported on stderr. Both always run: a failing fixture cleanup does not stop `tearDownOnce()`.

### Hooks that take fixtures

`setUp()` and `tearDown()` can ask for fixtures by parameter name, and they get the same instance the test gets:

```php
$tester->fixture('mailer', fn() => new FakeMailer());

$tester->setUp(function (FakeMailer $mailer): void {
    $mailer->allowDomain('example.com');
});

$tester->tearDown(function (TestResult $result, FakeMailer $mailer): void {
    if (!$result->isSuccess) {
        file_put_contents('mail.log', $mailer->dump());
    }
});
```

Fixture parameters can sit anywhere in the list because they are matched by name. The other parameters are filled in order from the hook's usual values, and values nobody asked for are not used. For `tearDown()`, those values are the result, then the setUp value.

### Replacing a fixture for every test

Taken **by reference**, a fixture can be replaced inside `setUp()`. The test then receives the new value, and the fixture's cleanup runs on it:

```php
$tester->fixture('clock', fn(): ?Clock => null);

$tester->setUp(function (?Clock &$clock): void {
    $clock = new FrozenClock('2026-01-01 12:00');
});

$tester->define('stamps_the_order_with_the_current_time', function (Clock $clock): void {
    $order = new Order(clock: $clock);

    Assert::exact('2026-01-01 12:00', $order->createdAt());
});
```

This is handy when a fixture's default works for most suites but this one needs a different value. For a shared fixture, the replacement holds for the rest of the suite.

## Skipping

```php
$tester->define('reads_from_postgres', function (): void {
    Assert::skipIf(!extension_loaded('pdo_pgsql'), 'pdo_pgsql is not installed');

    // ...
});

$tester->define('handles_leap_seconds', function (): void {
    Assert::skip('Waiting for the clock library to support leap seconds');
});
```

`Assert::skip(string $reason = 'Skipped.')` stops the test immediately. `Assert::skipIf(bool $condition, string $reason)` does the same when the condition is true. A skipped test is not a failure. It shows up with its reason:

```text
[-] reads_from_postgres skipped: pdo_pgsql is not installed
```

Always give a reason. A skip without one is a test someone forgot about.

## Return values and output

A test can return a value. It never affects the outcome, but it is recorded with the result and shown by the Test Explorer, the detailed `StringTestWriter` and the agent output. Returning the object under test can make a failure much easier to understand.

Anything a test prints (`echo`, `print_r`, `var_dump`) is captured per test instead of being sprayed across the console, and that includes output from `setUp()` and `tearDown()`. The same writers show it next to the test, and the JUnit report puts it into `<system-out>`:

```text
Test:     creates_an_order
Status:   Passed
Time:     0.00ms
Result:   [
    'order' => 42 (int),
]
Output:
    creating order
```

The default compact output shows neither, to keep a run of a thousand tests readable. See [output and reports](output.md) for the writers.

PHP warnings and notices are a different channel: they go to the run log in `.microunit/logs/`, not to the console.

## Helper functions

Helpers are plain functions. When several tests need the same check, give it a name:

```php
function assertValidSlug(string $slug): void
{
    Assert::matchesRegex('/^[a-z0-9-]+$/', $slug, "'$slug' is not a valid slug");
}

$tester->define('slugifies_titles', function (): void {
    assertValidSlug(Slug::from('Hello World'));
});
```

Assertions inside helpers count as assertions of the test. When one fails, the Test Explorer, the CI annotations and the agent output point to the line in the test file that called the helper. That is usually the line you want to look at.

Two things to watch:

- Test files are included into one PHP process, so functions defined in them are global. Two files that both declare `function makeUser()` produce a fatal "Cannot redeclare" error. Give helpers specific names, or move shared helpers into a namespaced file in `tests/Support/` that you load with [`withBootstrapFile()`](configuration.md#tests-and-discovery) or Composer's `autoload-dev`.
- Keep helpers small and obvious. A helper that hides the interesting part of a test makes it harder to read, which defeats the point.

## Test ids

Every test has an id of the form `Suite::name`:

```text
Cart::starts_empty
Cart::applies_quantity_single item
Cart::applies_quantity_three items
Cart::sums_the_line_totals#2
```

Data sets add their key to the name, and a duplicate name gets `#2`, `#3` and so on.

Ids are what `--filter` matches against (a case-insensitive substring, or a regular expression between slashes), what `--failed` remembers, and what the Test Explorer and the agent output use. `--list` prints them:

```bash
vendor/bin/microunit --list
vendor/bin/microunit --filter='/^Cart::applies_quantity_/'
```

See the [CLI page](cli.md#selecting-tests) for every way to select tests.

## Where tests live

MicroUnit looks for test files in the test directory, recursively, and includes every file whose name matches one of the file patterns. The defaults:

- **Directory:** `withTestDir()` if set; otherwise `tests/` next to the config file.
- **Pattern:** `*-tests.php`. Patterns are globs matched against the file name only, case-insensitively, with `*`, `?`, `[abc]` and `[!abc]`.
- **Skipped directories:** `vendor`, `node_modules`, `.git` and `.microunit` are never searched.

```php
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withTestFilePatterns('*Test.php', '*-tests.php')
    ->build();
```

A file you pass on the command line, like `vendor/bin/microunit tests/CartTest.php`, runs even if it does not match a pattern. A directory you pass is searched with the configured patterns.

### Test groups

When a project has distinct sets of tests, such as fast unit tests and slow integration tests, name them with `withTestGroup()`:

```php
return MicroUnitConfigBuilder::create()
    ->withTestGroup('unit', 'tests/Unit')
    ->withTestGroup('integration', 'tests/Integration', '*IntegrationTest.php')
    ->withTestFilePatterns('*Test.php')
    ->build();
```

`withTestGroup(string $name, string $directory, string ...$filePatterns)` adds a group. Without patterns, the group uses the configured test file patterns. Then:

```bash
vendor/bin/microunit                          # every group
vendor/bin/microunit --group=unit             # only the unit tests
vendor/bin/microunit --group=unit,integration # both, explicitly
```

An unknown group name is a usage error (exit code `2`) that lists the configured groups. If you also set `withTestDir()`, that directory belongs to no group: it runs when no `--group` is given and is left out when one is. Group directories are searched recursively, so do not nest one group's directory inside another's unless you want those files in both.

Groups are about which files run. To pick individual tests, use `--filter`.

## Related

- [Assertions](assertions.md) for everything `Assert` can check, and [fluent assertions](fluent-assertions.md) for the chained style.
- [Mocking](mocking.md) for test doubles and [snapshots](snapshots.md) for large outputs.
- [Integration testing](integration-testing.md) for databases, HTTP and servers.
- [Configuration](configuration.md) for every option.
