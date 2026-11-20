---
layout: default
title: Migrating from PHPUnit
description: Convert a PHPUnit suite with microunit migrate, see what needs manual work, and switch over while both suites run side by side.
---

# Migrating from PHPUnit

Nobody rewrites two thousand tests by hand to try a new test runner, and nobody should have to. `microunit migrate` reads your PHPUnit test classes and writes equivalent MicroUnit test files next to them: assertions, fixtures, lifecycle hooks, helpers, data providers, expected exceptions and PHPUnit mocks included. Your original tests are never touched, so both suites can run side by side until you trust the new one. Whatever the tool can't translate safely is not guessed at: it becomes a skipped test that carries the reason and the original code.

## Running it

```bash
vendor/bin/microunit migrate                          # tests/ into tests-microunit/
vendor/bin/microunit migrate tests/Unit --out=tests-new
```

Migrating needs no PHPUnit installation and runs none of your code. It parses the source files. Here is a small project:

```text
! PriceCalculatorTest.php  6 migrated, 1 need manual work

1 test classes, 6 tests migrated, 1 need manual work (written as skipped tests).
Details: tests-microunit/migration-report.json

✔ Created microunit.config.php for the migrated tests (from your phpunit.xml).
Run them with vendor/bin/microunit
```

A file marked `✔` migrated completely. A file marked `!` has tests or helpers that need a look, and the reasons are printed underneath.

## What a migrated test looks like

This PHPUnit class:

```php
final class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculator(vatRate: 0.2);
    }

    public function testAddsVat(): void
    {
        $this->assertSame(1200, $this->calculator->gross(1000));
    }

    #[DataProvider('discounts')]
    public function testAppliesDiscounts(int $percent, int $expected): void
    {
        $this->assertEquals($expected, $this->calculator->discounted(1000, $percent), 'discount');
    }

    public static function discounts(): array
    {
        return ['none' => [0, 1000], 'half' => [50, 500]];
    }

    public function testRejectsNegativePrices(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('negative');

        $this->calculator->gross(-1);
    }

    public function testSendsTheInvoice(): void
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects($this->once())->method('send')->with('ada@example.com')->willReturn(true);

        $this->assertTrue($this->calculator->sendInvoice($mailer, 'ada@example.com'));
    }
}
```

becomes this (unchanged output of `microunit migrate`, shortened to the same tests):

```php
namespace App\Tests\PriceCalculatorTest;

use App\Mailer;
use App\PriceCalculator;
use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertMock;
use MicroUnit\Mock\MicroMock;
use MicroUnit\Mock\MockBuilder;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('PriceCalculator');

$tester->fixture('calculator', fn(): PriceCalculator => new PriceCalculator(vatRate: 0.2));

$tester->define('adds_vat', function (PriceCalculator $calculator): void {
    Assert::exact(1200, $calculator->gross(1000));
});

$tester->defineGroup('applies_discounts', function (PriceCalculator $calculator, int $percent, int $expected): void {
    Assert::equals($expected, $calculator->discounted(1000, $percent), 'discount');
}, discounts());

$tester->define('rejects_negative_prices', function (PriceCalculator $calculator): void {
    Assert::throws(fn() => $calculator->gross(-1), \InvalidArgumentException::class, 'negative');
});

$tester->define('sends_the_invoice', function (PriceCalculator $calculator): void {
    $mailer = MockBuilder::create(Mailer::class)->disableOriginalConstructor()->build()->newInstance();
    MicroMock::of($mailer)->returns('send', true);
    Assert::isTrue($calculator->sendInvoice($mailer, 'ada@example.com'));
    AssertMock::begin($mailer)->isCalledOnce('send')->isOnlyCalledWithMatchingArgs('send', fn(array $arguments): bool => ($arguments[0] ?? null) == 'ada@example.com');
});

function discounts(): array
{
    return ['none' => [0, 1000], 'half' => [50, 500]];
}
```

Every migrated file gets its own namespace (the original namespace plus the class name), so helper functions from different test classes never collide.

## What it translates

**Assertions, names and argument order.** MicroUnit uses PHPUnit's argument order (expected first, actual second, message last), so most assertions only change their name: `assertSame` becomes `Assert::exact`, `assertEquals` becomes `Assert::equals`, `assertCount` becomes `Assert::countEquals`, `assertIsInt($x)` becomes `Assert::isType('int', $x)`, and so on. Assertions without a direct counterpart are spelled out as an equivalent expression (`assertStringEqualsFile` compares with `file_get_contents()`, `assertNotInstanceOf` becomes an `Assert::isFalse(... instanceof ...)`). Calls written as `$this->assertX()`, `self::assertX()`, `static::assertX()` and `Assert::assertX()` are all recognised. Named arguments (`assertCount(expectedCount: 2, haystack: $items)`) are written in PHPUnit's order without their names, since MicroUnit names its parameters differently; only `message:` stays named. `$this->fail()`, `markTestSkipped()` and `markTestIncomplete()` become `Assert::fail()` and `Assert::skip()`.

**Properties become fixtures.** A property assigned in `setUp()` (or one with a default value) becomes a `$tester->fixture()`, and every test that used `$this->calculator` receives `$calculator` as a parameter. Static properties assigned in `setUpBeforeClass()` become shared fixtures (`shared: true`), created once per suite. A local variable of `setUp()` that several properties are built from (`$connection = new Connection();` passed to a repository and a service) becomes a fixture of its own, so both fixtures still receive the same object. Static properties that no method changes, not even through a reference such as `sort(self::$items)`, are inlined as constants.

**Lifecycle hooks.** `setUp()`, `tearDown()`, `setUpBeforeClass()` and `tearDownAfterClass()` become `$tester->setUp()`, `tearDown()`, `setUpOnce()` and `tearDownOnce()`, with `parent::setUp()` calls inlined. Code in `tearDownAfterClass()` that only deals with one shared fixture (`rmdir(self::$dir)`) becomes that fixture's `cleanup:`, since `setUpOnce()` and `tearDownOnce()` cannot receive fixtures; other code there that uses a shared fixture is reported.

**Helpers become functions.** Every non-test method of the class becomes a plain function in the migrated file. Properties a helper reads or writes are passed in as parameters, and helpers that only set expectations (say, a custom `expectInvalidAmount()` that calls `expectException()`) are expanded where a test calls them. Such a helper called from anywhere other than the top level of a test is reported.

**Inherited base classes and traits.** Methods, properties and constants from abstract base classes and traits are merged into each test class. They are found among the migrated files first, and otherwise through the PSR-4 maps (`autoload` and `autoload-dev`) in your `composer.json`. A shared `TestCase` that lives in `src/Testing/` is picked up without any extra step.

**Data providers and `#[TestWith]`.** `#[DataProvider]` and `@dataProvider` become a `defineGroup()` whose data sets come from the provider (now a function). Provider keys become the data set names, so `'half'` turns into the test `applies_discounts_half`. `#[TestWith]`, `#[TestWithJson]` and `@testWith` (one data set per line) become inline data sets. `#[DataProviderExternal(Prices::class, 'all')]` calls `Prices::all()`, with the class imported.

**Expected exceptions and output.** Everything after `expectException()`, `expectExceptionMessage()`, `expectExceptionCode()`, `expectExceptionMessageMatches()` or `expectExceptionObject()` is wrapped in `Assert::throws()`. That includes the case where the expectation sits inside an `if` and only applies to some data sets. `expectOutputString()` and `expectOutputRegex()` capture the output with `ob_start()` and compare it at the end.

**Requirements.** `#[RequiresPhp]`, `#[RequiresPhpExtension]`, `#[RequiresOperatingSystem]`, `#[RequiresOperatingSystemFamily]` and `#[RequiresFunction]`, on the test or on the class, and the matching `@requires` annotations become `Assert::skipIf()` lines at the start of the test, each with a short reason such as `Requires the intl extension.` A requirement without a translation (a version constraint like `^8.1`, `#[RequiresMethod]`, `#[RequiresSetting]`) makes the test need manual work rather than run where PHPUnit would skip it.

**PHPUnit mocks become MicroUnit mocks.** `createMock()`, `createStub()`, `createPartialMock()`, `createConfiguredMock()` and `getMockBuilder()->...->getMock()` become `MockBuilder::create(...)->build()->newInstance()`. Stubbing (`->method('x')->willReturn(...)`, `willReturnOnConsecutiveCalls()`, `willThrowException()`, ...) becomes `MicroMock::of($mock)->returns(...)` and friends. Expectations (`expects($this->once())->method('x')->with(...)`) become `AssertMock` checks that run at the end of the test, which is exactly when PHPUnit verifies them. Expectations set in `setUp()` are checked after every test. See [Mocking](mocking.md) for the MicroUnit side.

**Mockery keeps working.** Tests that use Mockery stay as they are; Mockery is a library, not a PHPUnit feature. If the class used `MockeryPHPUnitIntegration`, the migrated suite calls `Mockery::close()` after every test, so expectations are still verified.

**Helper classes next to the tests.** Test files often declare a small stub class or function next to the test class. Those declarations are kept, in their original namespace, in a `*.support.php` file beside the migrated test (`ParserTest.support.php`), which the migrated test loads with `require_once`. The file doesn't match `*Test.php`, so it is never mistaken for a test file. A file that declares several test classes gets one migrated file per class, named after it (`FooTest.php`, `BarTest.php`).

**`__DIR__` keeps pointing at the originals.** A test that reads `__DIR__ . '/fixtures/input.txt'` still finds its fixture: `__DIR__` and `__FILE__` are rewritten relative to the migrated file, so they point at the original directory (`__DIR__ . '/../../tests/Unit' . '/fixtures/input.txt'`). Fixture files don't need to be copied.

**Framework test cases.** Classes that extend Laravel's or Orchestra Testbench's `TestCase`, Symfony's `KernelTestCase`/`WebTestCase` or CodeIgniter's `CIUnitTestCase` keep running inside the real framework test case. Calls like `$this->get('/')` go through the framework proxy (`Laravel::get('/')`), traits such as `RefreshDatabase` become `Laravel::uses($tester, RefreshDatabase::class)`, and the generated config enables the integration. The [frameworks](frameworks.md) page explains how that hosting works.

**The config, from `phpunit.xml`.** If there is no `microunit.config.php` yet, `migrate` writes one from `phpunit.xml` (or `phpunit.xml.dist`, or `phpunit.dist.xml`):

```php
// Generated by `microunit migrate`. Every option: https://mitarnik04.github.io/MicroUnit/configuration
return MicroUnitConfigBuilder::create()
    ->withTestDir('tests-microunit')
    ->withTestFilePatterns('*Test.php')
    ->withBootstrapFile('tests-microunit/bootstrap.php')
    ->build();
```

The bootstrap file carries over (a plain `vendor/autoload.php` is dropped, MicroUnit loads Composer's autoloader itself). `<env>`, `<server>` and `<ini>` entries go into a small generated `tests-microunit/bootstrap.php`, which then includes your original bootstrap. With a framework integration, the environment variables are handed to the integration instead. If a `microunit.config.php` already exists, `migrate` leaves it alone and tells you to point it at the new directory with `withTestDir()` and `withTestFilePatterns('*Test.php')`.

## What needs manual work

Some PHPUnit features have no honest one-to-one translation:

- **Process isolation.** `#[RunInSeparateProcess]`, `@runInSeparateProcess` and `#[PreserveGlobalState]`. MicroUnit runs tests in one process (or one worker per suite with [parallel runs](parallel.md)), so a test that needs a fresh process needs a different approach, typically resetting the global state it touches.
- **`#[Depends]`.** MicroUnit tests are independent and can run in any subset. Move the shared setup into a fixture.
- **Code that uses the test class itself.** `$this` passed around as an object, `get_class($this)`, or helper functions next to the test that write to the test class's static properties. When helper code next to the test needs the test class, none of that class's tests can run after migration, and all of them are reported with the reason.
- **Unusual mock usage.** Invocation matchers and `will()` arguments `migrate` doesn't recognise, mocks configured from a non-literal array, or expectations set in a helper that is neither a test nor `setUp()`.
- **Calls into a base class `migrate` can't see.** A parent class that is neither among the migrated files nor reachable through composer.json's PSR-4 map is reported, and tests that call its methods need a look.

Nothing is dropped silently. A test that needs manual work is written as a skipped test with the reason and the original code:

```php
$tester->define('adds_to_the_stack', function (): void {
    Assert::skip('Needs manual migration: depends on another test (#[Depends]); MicroUnit tests are independent.');

    // Original PHPUnit code:
    // {
    //     $stack[] = 'x';
    //     $this->assertNotEmpty($stack);
    // }
});
```

It shows up as skipped in every run, with its reason, and in the [Test Explorer](test-explorer.md) you can filter for skipped tests to get your to-do list. A helper function that can't be translated throws a `LogicException` with the same "Needs manual migration" message and keeps the original code as a comment, so the tests that call it fail loudly instead of passing by accident.

### migration-report.json

The output directory also gets `migration-report.json`, the machine-readable version of the summary:

```json
{
    "generatedAt": "2026-11-19T00:50:20+00:00",
    "suites": [
        {
            "class": "App\\Tests\\PriceCalculatorTest",
            "source": "/app/tests/PriceCalculatorTest.php",
            "target": "/app/tests-microunit/PriceCalculatorTest.php",
            "suite": "PriceCalculator",
            "migratedTests": ["adds_vat", "applies_discounts", "rejects_negative_prices", "sends_the_invoice", "starts_empty", "uses_the_helper"],
            "skippedTests": {
                "adds_to_the_stack": ["depends on another test (#[Depends]); MicroUnit tests are independent."]
            },
            "issues": [],
            "methods": {
                "testAddsVat": "adds_vat",
                "testAddsToTheStack": "adds_to_the_stack"
            }
        }
    ],
    "totals": {"files": 1, "migrated": 6, "skipped": 1}
}
```

`issues` collects class-level problems (helpers, traits, base classes), `methods` maps every PHPUnit method name to its MicroUnit test name, and classes that could not be translated at all appear under `failedClasses`. It is a handy input for an AI agent working through the remaining tests.

## PHPUnit to MicroUnit at a glance

| PHPUnit | MicroUnit |
| --- | --- |
| `class CartTest extends TestCase` | a file with `$tester = TestSetup::getTester('Cart');` |
| `public function testAddsItems()` or `#[Test]` | `$tester->define('adds_items', function () { ... });` |
| `$this->assertSame($expected, $actual)` | `Assert::exact($expected, $actual)` |
| `$this->assertEquals($expected, $actual)` | `Assert::equals($expected, $actual)` |
| `$this->assertTrue($value)` | `Assert::isTrue($value)` |
| `$this->assertCount(3, $items)` | `Assert::countEquals(3, $items)` |
| `$this->assertInstanceOf(Foo::class, $x)` | `Assert::instanceOf(Foo::class, $x)` |
| `$this->assertEqualsCanonicalizing($a, $b)` | `Assert::equivalent($a, $b)` |
| `$this->assertIsString($x)` | `Assert::isType('string', $x)` |
| property set in `setUp()` | `$tester->fixture('name', fn() => ...)`, injected by parameter name |
| `setUp()` / `tearDown()` | `$tester->setUp(...)` / `$tester->tearDown(...)` |
| `setUpBeforeClass()` / `tearDownAfterClass()` | `$tester->setUpOnce(...)` / `$tester->tearDownOnce(...)` |
| static property shared by the class | `$tester->fixture('name', fn() => ..., shared: true)` |
| `#[DataProvider('cases')]` | `$tester->defineGroup('name', function (...) { ... }, cases());` |
| `#[TestWith([1, 2])]` | `defineGroup()` with an inline array of data sets |
| `expectException()` + `expectExceptionMessage()` | `Assert::throws(fn() => ..., Type::class, 'message')` |
| `expectOutputString('x')` | `Assert::prints(fn() => ..., 'x')` |
| `markTestSkipped('why')` | `Assert::skip('why')` |
| `#[RequiresPhpExtension('intl')]` | `Assert::skipIf(!extension_loaded('intl'), '...')` |
| `$this->createMock(Mailer::class)` | `MockBuilder::create(Mailer::class)->build()->newInstance()` |
| `->method('send')->willReturn(true)` | `MicroMock::of($mailer)->returns('send', true)` |
| `->expects($this->once())->method('send')` | `AssertMock::begin($mailer)->isCalledOnce('send')` |
| `$this->fail('why')` | `Assert::fail('why')` |
| `#[Depends]` | no equivalent: share setup through a fixture |
| `#[RunInSeparateProcess]` | no equivalent: reset the global state instead |
| `phpunit.xml` | `microunit.config.php` |
| `vendor/bin/phpunit --filter=adds` | `vendor/bin/microunit --filter=adds` |

The [assertions](assertions.md) page lists every assertion; [writing tests](writing-tests.md) covers fixtures, hooks and data sets in depth.

## A migration strategy that doesn't hurt

The safe way is to let both suites run for a while and only switch when the numbers agree.

1. **Migrate.** Run `vendor/bin/microunit migrate` on a clean working tree. Everything lands in `tests-microunit/`, so `git status` shows exactly what was added.
2. **Run both.** `vendor/bin/phpunit` and `vendor/bin/microunit` side by side. The test counts should match, apart from the skipped "Needs manual migration" tests. A test that passes under PHPUnit and fails under MicroUnit after migration is either a translation problem worth reporting or a test that depended on PHPUnit's behaviour in a way you'll want to know about.
3. **Work through the list.** `migration-report.json` and the skipped tests are your to-do list. Typical items: replace a `#[Depends]` with a fixture, rewrite one unusual mock, reset some global state by hand.
4. **Decide where the source of truth is.** While PHPUnit is still the suite your team maintains, keep editing the PHPUnit originals and re-run `migrate` to refresh the output. Re-running overwrites the migrated files, including hand-made fixes, so once you start fixing migrated files by hand, stop re-running it (or migrate into a fresh `--out` directory and compare).
5. **Switch.** Move the migrated tests where you want them (rename `tests-microunit` to `tests`, or keep the name), update `withTestDir()`, run the suite in CI, and delete the PHPUnit originals and `phpunit.xml`.
6. **Remove PHPUnit, if you can.** For plain PHP projects, `composer remove --dev phpunit/phpunit` is the last step. Projects using the Laravel, Symfony or CodeIgniter integration keep `phpunit/phpunit` installed, because the framework's own test case classes are built on it.

For CI, run both suites in the same pipeline during the transition, so a regression in either shows up immediately. The [CI/CD](ci-cd.md) page has ready-made jobs.

## Proven on real projects

`migrate` was developed against the test suites of real, widely used open-source libraries, not toy examples: brick/math, symfony/yaml, nesbot/carbon, league/commonmark, monolog, ramsey/uuid, webmozart/assert, nikic/php-parser and egulias/email-validator. Their suites cover a lot of ground: deep base-class hierarchies, huge data providers, PHPUnit mocks, Mockery, fixtures read via `__DIR__`, helper classes declared next to tests.

For the [benchmarks](benchmarks.md), six of those suites were migrated in one go and run on both sides. The numbers come from the published exclusion lists:

| Project | Migrated tests that pass | Test methods left to fix by hand |
|---|---:|---:|
| brick/math | 24,782 | 8 |
| nesbot/carbon | 6,233 | 104 |
| webmozart/assert | 4,942 | 42 |
| league/commonmark | 4,340 | 106 |
| symfony/yaml | 1,427 | 0 |
| monolog/monolog | 1,197 | 90 |

"Left to fix by hand" counts the methods `migrate` skipped with a reason plus the ones that failed after translation. Typical reasons: tests that run in a separate process, tests that use `$this` as an object, unusual uses of PHPUnit's mock API, and, in webmozart/assert, data sets that check PHPUnit's own `TestCase` class. The migration report and the skipped tests tell you exactly which ones.

## Related pages

- [Getting started](getting-started.md) if you'd rather start fresh.
- [Writing tests](writing-tests.md) for the test syntax you are migrating to.
- [Mocking](mocking.md) and [Frameworks](frameworks.md) for the two areas where migrated code looks most different.
- [AI agents](ai-agents.md): `init --skill` teaches your agent MicroUnit, which helps when it works through the remaining tests.
