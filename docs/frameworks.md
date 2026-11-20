---
layout: default
title: Laravel, Symfony and CodeIgniter
description: Run MicroUnit tests inside Laravel's, Symfony's or CodeIgniter 4's own test case, with every framework helper available.
---

# Laravel, Symfony and CodeIgniter

Framework testing layers are big, battle-tested and built on PHPUnit. Rewriting them would be a waste of everyone's time, so MicroUnit doesn't. Instead, every test runs inside the framework's own test case: Laravel's `TestCase`, Symfony's `WebTestCase` or CodeIgniter's `CIUnitTestCase`. The framework's `setUp()` and `tearDown()` run exactly as they would under PHPUnit, and a small static proxy (`Laravel::`, `Symfony::`, `CodeIgniter::`) hands you every helper the framework offers. You keep writing tests the MicroUnit way, top to bottom, and the framework keeps doing what it's good at.

## Before you start: PHPUnit stays installed

MicroUnit itself has zero dependencies, and that does not change here. But the frameworks' test cases extend `PHPUnit\Framework\TestCase`, so `phpunit/phpunit` has to be installed for an integration to work. Laravel, Symfony and CodeIgniter projects already ship it in `require-dev`, so in practice there is nothing to do. Just don't remove it. PHPUnit 10 through 13 are supported.

If the base test case can't be loaded, the first test tells you so:

```
The test case class Illuminate\Foundation\Testing\TestCase does not exist. Is the framework (and phpunit/phpunit) installed?
```

## How it works

For each test, MicroUnit generates a tiny subclass of the framework's base test case (plus any traits you asked for, like `RefreshDatabase`), creates a fresh instance and runs this sequence:

1. The framework's `setUp()`: boots the application, applies traits such as `RefreshDatabase`.
2. Your suite's `setUp()`, then the fixtures the test asks for.
3. The test itself.
4. Your suite's `tearDown()` and fixture cleanup.
5. The framework's `tearDown()`: resets framework state. Laravel's, for example, verifies Mockery expectations, resets the clock and flushes the application.

Because the framework's `setUp()` runs first, your fixtures and `setUp()` can already use the framework. A fixture that creates a user through a factory just works:

```php
$tester->fixture('admin', fn() => User::factory()->admin()->create());
```

`setUpBeforeClass()` runs once per generated class and `tearDownAfterClass()` at the end of the run, the same rhythm PHPUnit uses per test class.

### The proxies

`Laravel::`, `Symfony::` and `CodeIgniter::` forward every static call to the test case of the running test. `Laravel::getJson('/api/products')` is `$this->getJson('/api/products')` inside a PHPUnit test, nothing more. Protected helpers work too, and so does anything your own base test case adds.

The proxies carry `@method` docblocks for the common helpers, so your IDE autocompletes them. Methods missing from the docblock still work; they just don't autocomplete.

Call a method the test case doesn't have and you get a pointed error instead of a generic one:

```
BadMethodCallException: Illuminate\Foundation\Testing\TestCase has no method assertDatabaseHas(). Is a trait missing? Add it with the proxy's uses().
```

Calling a framework helper outside a running test (in a test file's top-level code, or in `setUpOnce()`) throws a `LogicException`, because there is no test case to forward to yet. `uses()` and `useTestCase()` are the exception: they belong at the top of the file.

### Framework assertions are first-class

Framework helpers assert through PHPUnit. MicroUnit watches PHPUnit's assertion counter around every test and adds the difference to the test's own count, so a test that only calls `->assertOk()` is not flagged as "no assertions". Expectations the framework adds to the test case itself count too, like the Mockery expectations (`shouldReceive()->once()`) Laravel verifies in `tearDown()`.

When a framework assertion fails, MicroUnit translates the `PHPUnit\Framework\AssertionFailedError` into a normal MicroUnit failure. No PHPUnit stack trace, no crash report: you get the message, and when PHPUnit had a comparison (expected vs. actual) you get MicroUnit's diff. Very long messages are cut to 12 lines and object dumps are collapsed to the class name, because nobody reads a 300-line `var_dump` of a response object.

```
[✖] creating_a_product_requires_a_name (41.20ms)
    Expected response status code [201] but received 422.
```

## Configuration

Each framework gets one integration class, passed to `withFramework()` in `microunit.config.php`. All three accept the same named arguments:

| Argument | What it does |
| --- | --- |
| `testCase` | The base test case every test runs in. Defaults to the framework's own (see below). |
| `traits` | Traits every test gets, like `use RefreshDatabase;` in each PHPUnit class. |
| `environment` | Environment variables, the equivalent of `<env>` in `phpunit.xml`. |

The environment variables are written with `putenv()`, `$_ENV` and `$_SERVER` once, before any test file loads. They always win over values already in the environment, which is different from PHPUnit's `<env>` without `force="true"`.

`microunit init` detects the framework from `composer.json` and writes the `withFramework(...)` line for you. Read on before you run anything, though, especially if you use Laravel.

### Laravel

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Integration\Laravel\LaravelIntegration;

return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withFramework(new LaravelIntegration(
        traits: [RefreshDatabase::class],
        environment: [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array',
        ],
    ))
    ->build();
```

The default test case is your project's `Tests\TestCase` if it exists (so its helpers are available), otherwise `Illuminate\Foundation\Testing\TestCase`.

**Set the environment.** This is the one thing I'd tattoo on the back of every Laravel developer's hand. `APP_ENV` defaults to `testing`, but the database comes from your `.env` unless you say otherwise, and `RefreshDatabase` will happily migrate whatever database that is from scratch. MicroUnit does not read `phpunit.xml` at run time, so copy its `<env>` entries (`DB_CONNECTION`, `DB_DATABASE` and friends) into `environment:`. `microunit init` and `microunit migrate` both do this for you.

### Symfony

```php
<?php

use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Integration\Symfony\SymfonyIntegration;

return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withBootstrapFile('tests/bootstrap.php')
    ->withFramework(new SymfonyIntegration(
        environment: ['KERNEL_CLASS' => 'App\Kernel'],
    ))
    ->build();
```

The default test case is `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`, and `APP_ENV` defaults to `test` (anything you pass in `environment` is merged on top).

Symfony needs its test bootstrap: `tests/bootstrap.php` loads `.env`, `.env.test` and friends through Dotenv. Point `withBootstrapFile()` at it. `microunit init` adds that line automatically when the file exists. `KERNEL_CLASS` usually lives in `phpunit.xml.dist` as a `<server>` entry, so pass it in `environment` unless your `.env.test` already sets it.

### CodeIgniter 4

```php
<?php

use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Integration\CodeIgniter\CodeIgniterIntegration;

return MicroUnitConfigBuilder::create()
    ->withTestDir('tests')
    ->withFramework(new CodeIgniterIntegration(
        traits: [FeatureTestTrait::class, DatabaseTestTrait::class],
    ))
    ->build();
```

The default test case is `CodeIgniter\Test\CIUnitTestCase`, and `CI_ENVIRONMENT` defaults to `testing`.

CodeIgniter needs its test bootstrap before anything runs. The integration finds `vendor/codeigniter4/framework/system/Test/bootstrap.php` by searching upwards from the project directory, defines `HOMEPATH` (normally done by `phpunit.xml`) and loads it. No `withBootstrapFile()` needed. If the framework isn't installed, the run stops with `CodeIgniter 4 was not found`.

Unlike Laravel's test case, `CIUnitTestCase` ships without the HTTP and database helpers. They live in `FeatureTestTrait` and `DatabaseTestTrait`, so add the ones you use, globally in `traits:` or per suite.

## Per-suite traits

In PHPUnit you write `use RefreshDatabase;` in the classes that need it. In MicroUnit you tell the proxy, right below `getTester()`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use MicroUnit\Integration\Laravel\Laravel;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Products');
Laravel::uses($tester, RefreshDatabase::class);
```

`Symfony::uses()` and `CodeIgniter::uses()` work the same way. Suite traits are added to the ones from the config, duplicates are ignored, and they only affect the tests of that suite. Database-heavy suites get `RefreshDatabase`, pure HTTP suites stay fast.

## Per-suite base test case

Some suites need a different base class: one with extra helpers, or CodeIgniter's database properties set. That's `useTestCase()`, MicroUnit's version of `extends`:

```php
CodeIgniter::useTestCase($tester, Tests\Support\SeededTestCase::class);
```

It replaces the configured (or default) test case for that suite only. Traits from the config and from `uses()` still apply.

## Things to know

- **Only `setUp()` and `tearDown()` run.** PHPUnit-specific hooks such as methods marked with `#[Before]`/`#[After]` (or `@before`/`@after`) and PHPUnit extensions configured in `phpunit.xml` are not executed. Laravel's traits (`RefreshDatabase`, `DatabaseTransactions`, `WithFaker`) are wired through `setUp()`, as are CodeIgniter's `setUp{Trait}()` methods, so they work. A third-party trait that relies on `#[Before]` needs its setup called by hand.
- **Don't share framework objects across tests.** The framework's `tearDown()` throws the application away after every test. A `shared: true` fixture holding a model or a service from the container will point at a dead application in the next test. Share external resources (see [Integration testing](integration-testing.md)), not framework state.
- **Parallel runs are separate processes.** Each worker boots its own framework. In-memory SQLite is isolated per process automatically; a real database server is shared, so give workers separate databases or keep database suites sequential. See [Parallel runs](parallel.md).
- **Names collide.** Laravel's `Http` facade and MicroUnit's `MicroUnit\Toolkit\Http` share a short name. Alias one of them (`use MicroUnit\Toolkit\Http as HttpClient;`) if you need both in a file.

## Laravel recipes

Laravel's base test case includes everything (HTTP, database, auth, mocking, time), so you rarely need extra traits beyond `RefreshDatabase`.

### HTTP requests

```php
$tester->define('lists_products', function () {
    Product::factory()->count(3)->create();

    Laravel::getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

$tester->define('rejects_a_product_without_a_name', function () {
    Laravel::postJson('/api/products', ['price' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
```

`withHeaders()`, `withToken()`, `withoutMiddleware()` and friends return the test case, so they chain like in PHPUnit: `Laravel::withToken($token)->getJson('/api/me')`.

Prefer MicroUnit's assertions? `AssertResponse` reads Laravel's `TestResponse` directly:

```php
AssertResponse::begin(Laravel::getJson('/api/products/1'))
    ->isOk()
    ->jsonPathEquals('data.name', 'Hub');
```

### Acting as a user

```php
$tester->define('admins_can_create_products', function () {
    $admin = User::factory()->admin()->create();

    Laravel::actingAs($admin)
        ->postJson('/api/products', ['name' => 'Hub', 'price' => 25])
        ->assertCreated();

    Laravel::assertAuthenticatedAs($admin);
});
```

### Database assertions

```php
Laravel::assertDatabaseHas('products', ['name' => 'Hub']);
Laravel::assertDatabaseMissing('products', ['name' => 'Old hub']);
Laravel::assertDatabaseCount('products', 1);
Laravel::assertSoftDeleted($product);
Laravel::assertModelExists($product);
Laravel::seed(ProductSeeder::class);
```

### The container

`Laravel::app()` returns the running application. The `app()` helper and facades work as usual, because the application is booted.

```php
$prices = Laravel::app()->make(PriceCalculator::class);
Assert::exact(119, $prices->gross(100));
```

### Mocks and fakes

Laravel's Mockery helpers bind the mock into the container, and Laravel's `tearDown()` verifies the expectations:

```php
use Mockery\MockInterface;

$tester->define('charges_the_card_on_checkout', function () {
    Laravel::mock(PaymentGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('charge')->once()->andReturn(true);
    });

    Laravel::postJson('/api/checkout')->assertOk();
});
```

Or use a [MicroUnit mock](mocking.md) and hand it to the container with `instance()`:

```php
$gateway = MockBuilder::create(PaymentGateway::class)->returns('charge', true)->build();
Laravel::instance(PaymentGateway::class, $gateway->newInstance());
```

Facade fakes need no proxy at all. Their assertions go through PHPUnit, so they are counted and fail cleanly:

```php
Mail::fake();

Laravel::postJson('/api/orders', $order)->assertCreated();

Mail::assertSent(OrderConfirmation::class);
```

### Time travel

```php
Laravel::travelTo(now()->addMonth());
Laravel::travel(5)->minutes();
Laravel::freezeTime();
Laravel::travelBack();
```

Laravel resets the clock in its `tearDown()`, so a frozen clock doesn't leak into the next test.

### Artisan commands

```php
Laravel::artisan('products:import', ['file' => 'tests/products.csv'])
    ->expectsOutput('Imported 3 products.')
    ->assertExitCode(0);
```

## Symfony recipes

### HTTP requests

`Symfony::client()` creates the BrowserKit client and boots the kernel. The `assert*` helpers check the last response:

```php
$tester->define('the_product_page_shows_the_name', function () {
    $client = Symfony::client();
    $client->request('GET', '/products/hub');

    Symfony::assertResponseIsSuccessful();
    Symfony::assertSelectorTextContains('h1', 'Hub');
    Symfony::assertPageTitleContains('Hub');
});
```

For JSON APIs, `AssertResponse` reads Symfony responses directly:

```php
$client = Symfony::client();
$client->jsonRequest('POST', '/api/products', ['name' => 'Hub', 'price' => 25]);

AssertResponse::begin($client->getResponse())
    ->statusIs(201)
    ->jsonPathEquals('name', 'Hub');
```

Create the client before you touch the container. `Symfony::container()` boots the kernel, and Symfony refuses to create a client after that: calling `Symfony::container()` first and `Symfony::client()` second throws a `LogicException` about booting the kernel before `createClient()`.

### Acting as a user

```php
$client = Symfony::client();
$user = Symfony::container()->get(UserRepository::class)->findOneByEmail('admin@example.com');

$client->loginUser($user);
$client->request('GET', '/admin');

Symfony::assertResponseIsSuccessful();
```

### Database assertions

Symfony has no database assertions of its own. Fetch the repository from the container and assert with MicroUnit:

```php
$client->jsonRequest('POST', '/api/products', ['name' => 'Hub', 'price' => 25]);

$product = Symfony::container()->get(ProductRepository::class)->findOneBy(['name' => 'Hub']);
Assert::notNull($product);
Assert::exact(25, $product->getPrice());
```

### The container and services

`Symfony::container()` returns the test container, which also hands out private services:

```php
$calculator = Symfony::container()->get(PriceCalculator::class);
Assert::exact(119, $calculator->gross(100));
```

### Mocks

Replace a service in the test container with a [MicroUnit mock](mocking.md). The client reboots the kernel between requests by default, which would throw your replacement away, so switch that off first:

```php
$client = Symfony::client();
$client->disableReboot();

$gateway = MockBuilder::create(PaymentGateway::class)->returns('charge', true)->build();
Symfony::container()->set(PaymentGateway::class, $gateway->newInstance());

$client->request('POST', '/checkout');
Symfony::assertResponseIsSuccessful();
```

### Mail

```php
$client->request('POST', '/register', ['email' => 'ada@example.com']);
Symfony::assertEmailCount(1);
```

### Time travel

If your code reads the time through Symfony's Clock component, add `ClockSensitiveTrait` and freeze the clock with `mockTime()`. The trait restores the clock in an `#[After]` method, which MicroUnit doesn't run, so reset it in your suite's `tearDown()`:

```php
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

Symfony::uses($tester, ClockSensitiveTrait::class);
$tester->tearDown(fn() => Clock::set(new NativeClock()));

$tester->define('subscriptions_expire_after_a_month', function () {
    Symfony::mockTime('2026-01-01 10:00:00');
    // ...
});
```

## CodeIgniter recipes

These assume `FeatureTestTrait` and `DatabaseTestTrait` are configured, as in the config example above.

### HTTP requests

```php
$tester->define('lists_products', function () {
    CodeIgniter::get('/products')
        ->assertOK()
        ->assertSee('Hub');
});

$tester->define('creates_a_product', function () {
    CodeIgniter::post('/products', ['name' => 'Hub', 'price' => 25])
        ->assertRedirectTo('/products');
});
```

`withHeaders()`, `withBodyFormat('json')` and `withRoutes()` return the test case, so they chain: `CodeIgniter::withBodyFormat('json')->post('/api/products', $payload)`. `AssertResponse` also accepts CodeIgniter's `TestResponse`.

### Acting as a user

CodeIgniter keeps the logged-in user in the session:

```php
CodeIgniter::withSession(['user_id' => 1, 'role' => 'admin'])
    ->get('/admin')
    ->assertOK();
```

### Database assertions

```php
CodeIgniter::seed('ProductSeeder');
CodeIgniter::seeInDatabase('products', ['name' => 'Hub']);
CodeIgniter::dontSeeInDatabase('products', ['name' => 'Old hub']);
CodeIgniter::seeNumRecords(1, 'products', ['name' => 'Hub']);

$price = CodeIgniter::grabFromDatabase('products', 'price', ['name' => 'Hub']);
$id = CodeIgniter::hasInDatabase('products', ['name' => 'Cable', 'price' => 5]);
```

For suites that should migrate and seed automatically, give them a base test case with CodeIgniter's database properties set:

```php
namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;

abstract class SeededTestCase extends CIUnitTestCase
{
    protected $refresh = true;
    protected $seed = 'ProductSeeder';
}
```

```php
$tester = TestSetup::getTester('Products');
CodeIgniter::useTestCase($tester, SeededTestCase::class);
```

### Services and mocks

CodeIgniter swaps models through `Factories::injectMock()` and services through `Services::injectMock()`. Combine them with [MicroUnit mocks](mocking.md):

```php
use CodeIgniter\Config\Factories;

$model = MockBuilder::create(ProductModel::class)->returns('findAll', [])->build();
Factories::injectMock('models', ProductModel::class, $model->newInstance());

CodeIgniter::get('/products')->assertOK();
```

### Time travel

```php
use CodeIgniter\I18n\Time;

$tester->tearDown(fn() => Time::setTestNow());

$tester->define('orders_older_than_a_week_are_archived', function () {
    Time::setTestNow('2026-01-08 10:00:00');
    // ...
});
```

Reset it in `tearDown()` as shown, so the frozen time doesn't leak into other tests.

## Related

- [Getting started](getting-started.md): `microunit init` sets up the integration for you.
- [Migrating from PHPUnit](migrating-from-phpunit.md): converts existing framework tests and carries `phpunit.xml`'s `<env>` over.
- [Integration testing](integration-testing.md): fixtures and tools for tests against real services.
- [Configuration](configuration.md): every option on `MicroUnitConfigBuilder`.
