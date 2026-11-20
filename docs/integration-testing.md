---
layout: default
title: Integration testing
description: Test against real databases, real HTTP servers and the real file system, with fixtures that clean up after themselves.
---

# Integration testing

Unit tests tell you your classes do what you think. Integration tests tell you they still do it once a real database, a real web server and a real file system get involved, which is usually where the interesting bugs live. MicroUnit treats this as a first-class job: named fixtures that set up and tear down resources, suite-wide setup for the expensive stuff, and a small toolkit (`Database`, `HttpServer`, `Http`, `TemporaryDirectory`) that covers the common cases without pulling in a single package. Services that aren't available skip the test instead of turning the whole run red.

If you're testing a Laravel, Symfony or CodeIgniter app, start with [Laravel, Symfony and CodeIgniter](frameworks.md). Everything on this page works there too.

## Fixtures: resources with a name

A fixture is a named resource. Declare it once per suite with `fixture()`, and every test with a parameter of that name receives it:

```php
use MicroUnit\Assertion\Assert;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Toolkit\Database;

$tester = TestSetup::getTester('ProductRepository');

$tester->fixture('db', fn() => Database::sqlite(__DIR__ . '/schema.sql'));

$tester->define('stores_a_product', function (Database $db) {
    $repository = new ProductRepository($db->pdo);
    $repository->add('Hub', price: 25);

    Assert::exact(1, $db->count('products'));
});
```

The matching is by parameter **name**, not type. `$db` gets the `db` fixture. Parameters that don't match a fixture are filled with the usual positional values, so fixtures mix freely with `setUp()` results and data sets.

Fixtures are lazy. One is created the first time a test (or `setUp()`, or another fixture) asks for it, and a test that doesn't ask doesn't pay for it.

### Cleanup

Pass a `cleanup` callable and it runs after the test, whether the test passed or failed:

```php
$tester->fixture(
    'db',
    fn() => Database::connect(getenv('DB_DSN'), 'app', 'secret')->beginTransaction(),
    cleanup: fn(Database $db) => $db->rollBack(),
);
```

Cleanups run in reverse creation order, so a fixture is torn down before the fixtures it was built from. Every cleanup runs even if one of them throws. If a cleanup fails, an otherwise passing test fails with `Cleanup of fixture 'db' failed: ...`; a test that already failed keeps its original failure as the reported cause.

### Fixtures that use fixtures

A fixture factory can ask for other fixtures the same way a test does:

```php
$tester->fixture('db', fn() => Database::sqlite(__DIR__ . '/schema.sql'));
$tester->fixture('repository', fn(Database $db) => new ProductRepository($db->pdo));

$tester->define('finds_products_by_name', function (ProductRepository $repository, Database $db) {
    $db->insert('products', ['name' => 'Hub', 'price' => 25]);

    Assert::notNull($repository->findByName('Hub'));
});
```

Within one test, both get the same `Database` instance. Fixtures that depend on each other in a circle are reported as such instead of recursing forever.

### Shared fixtures for expensive resources

By default every test gets a fresh instance. That's what you want for a database you mutate, and exactly what you don't want for a web server that takes half a second to boot. `shared: true` creates the fixture once for the whole suite and cleans it up after the suite's last test:

```php
$tester->fixture('app', fn() => HttpServer::start('public/index.php'), shared: true);
```

Shared fixtures play well with [parallel runs](parallel.md): MicroUnit distributes whole suites to workers, so a suite's shared fixtures never get split across processes. A failing cleanup of a shared fixture is printed to stderr, since there's no single test left to blame; `tearDownOnce()` still runs afterwards.

## setUpOnce and tearDownOnce

For suite-wide setup that isn't naturally "one named thing", there's `setUpOnce()`. It runs once before the suite's first test, and `tearDownOnce()` runs once after the last one and receives the same value:

```php
$tester->setUpOnce(fn() => Database::connect('mysql:host=127.0.0.1;dbname=shop_test', 'shop', 'secret'));
$tester->tearDownOnce(fn(Database $db) => $db->execute('DELETE FROM products'));

$tester->define('reads_products', function (Database $db) {
    Assert::notEmpty($db->select('SELECT * FROM products'));
});
```

The value goes to `setUp()` if the suite has one; otherwise each test receives it as its first argument. If `setUpOnce()` throws, every test of the suite fails with `setUpOnce() of suite '...' failed: ...`. If it skips (see [below](#skipping-when-a-service-is-missing)), every test of the suite is skipped with that reason.

Need to share more than one thing? Return a `ValueBundle`: a small bag of named values with property access. Values set with `readonly: true` can't be overwritten by accident.

```php
use MicroUnit\Toolkit\ValueBundle;

$tester->setUpOnce(function (): ValueBundle {
    $shared = new ValueBundle();
    $shared->set('app', HttpServer::start('public/index.php'), readonly: true);
    $shared->set('db', Database::sqlite(__DIR__ . '/schema.sql'), readonly: true);

    return $shared;
});

$tester->tearDownOnce(fn(ValueBundle $shared) => $shared->app->stop());
```

My rule of thumb: reach for named fixtures first. They read better in the test signature, they're lazy, and they clean up on their own. `setUpOnce()` is for the odd case where the setup is a procedure rather than a resource.

## Database

`MicroUnit\Toolkit\Database` is a thin PDO wrapper for tests. The underlying PDO is public (`$db->pdo`) so you can pass it into the code you're testing. Errors throw exceptions (`PDO::ERRMODE_EXCEPTION`).

### Creating one

```php
// SQLite in memory, initialised from a schema file
$db = Database::sqlite(__DIR__ . '/schema.sql');

// SQLite in a file
$db = Database::sqlite(__DIR__ . '/schema.sql', path: __DIR__ . '/test.sqlite');

// Any PDO database
$db = Database::connect('pgsql:host=127.0.0.1;dbname=shop_test', 'shop', 'secret');
```

`sqlite()` skips the test when the `pdo_sqlite` extension is missing. `connect()` skips the test when the connection fails, whether the server is down or the PDO driver isn't installed, with the error in the reason:

```
[-] stores_orders skipped: Database not reachable (pgsql:host=127.0.0.1;dbname=shop_test): could not find driver
```

Relative file paths are resolved against the current working directory, so `__DIR__` is the safer choice.

### Working with it

| Method | Returns | What it does |
| --- | --- | --- |
| `runFile(string $sqlFile)` | `Database` | Runs every statement in an SQL file (schema, seeds). |
| `execute(string $sql, array $parameters = [])` | `int` | Runs a statement, returns the affected row count. |
| `select(string $sql, array $parameters = [])` | `list<array>` | All rows as associative arrays. |
| `selectOne(string $sql, array $parameters = [])` | `?array` | The first row, or `null`. |
| `insert(string $table, array $row)` | `string` | Inserts `column => value`, returns the new id. |
| `count(string $table)` | `int` | Number of rows in the table. |
| `beginTransaction()` | `Database` | Opens a transaction. |
| `rollBack()` | `void` | Rolls back the open transaction; does nothing if there is none. |

Statements are prepared, so parameters can be positional (`?`) or named (`:name`):

```php
$id = $db->insert('products', ['name' => 'Hub', 'price' => 25]);

$db->execute('UPDATE products SET price = :price WHERE id = :id', ['price' => 30, 'id' => $id]);

Assert::exact(['name' => 'Hub', 'price' => 30], $db->selectOne('SELECT name, price FROM products WHERE id = ?', [$id]));
```

### One transaction per test

Against a real database server, recreating the schema for every test gets slow. Open a transaction in the fixture and roll it back in the cleanup: every test sees the same starting data, and nothing it writes survives.

```php
$tester->fixture(
    'db',
    fn() => Database::connect(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASSWORD'))->beginTransaction(),
    cleanup: fn(Database $db) => $db->rollBack(),
);
```

`rollBack()` is a no-op without an open transaction, so it's safe as a cleanup even when the test committed or rolled back on its own.

## HttpServer

`HttpServer::start()` runs your application with PHP's built-in web server (`php -S`) on a free port, waits until it accepts connections, and returns a handle:

```php
use MicroUnit\Toolkit\HttpServer;

$tester->fixture('app', fn() => HttpServer::start('public/index.php'), shared: true);
```

The argument is either a document root directory or a router script like `public/index.php` (then its directory becomes the document root). It's resolved against the current working directory. Two optional arguments: `environment` (extra environment variables for the application process) and `host` (default `127.0.0.1`).

```php
HttpServer::start('public/index.php', environment: ['APP_ENV' => 'testing', 'DB_DSN' => 'sqlite::memory:']);
```

| Member | What it gives you |
| --- | --- |
| `url(string $path = '/')` | An absolute URL on the server: `$app->url('/orders?page=2')`. |
| `log()` | Everything the server process logged: requests and PHP errors. Gold when a request returns 500. |
| `stop()` | Stops the server. Also happens automatically when the object is destroyed. |
| `host`, `port` | Where it listens. |

If the server doesn't come up within 10 seconds, `start()` throws with the server's log in the message, so a typo in the router path or a fatal error in your bootstrap shows up right there.

## Http and HttpResponse

`MicroUnit\Toolkit\Http` is a tiny HTTP client built on PHP's stream wrappers, so it needs no cURL. Two decisions make it good for tests: redirects are **not** followed (you want to assert them), and 4xx/5xx responses are returned, not thrown.

```php
use MicroUnit\Toolkit\Http;

$response = Http::get($app->url('/products'));
$response = Http::get($app->url('/me'), headers: ['Authorization' => 'Bearer secret']);
$response = Http::post($app->url('/orders'), json: ['sku' => 'A1', 'quantity' => 2]);
$response = Http::put($app->url('/orders/1'), body: 'raw body', headers: ['Content-Type' => 'text/plain']);
$response = Http::patch($app->url('/orders/1'), json: ['quantity' => 3]);
$response = Http::delete($app->url('/orders/1'));
$response = Http::request('OPTIONS', $app->url('/orders'));
```

`json:` encodes the array and sets `Content-Type` and `Accept` to `application/json` (unless you set them yourself). If the server can't be reached at all, a `RuntimeException` says so. The timeout is 30 seconds.

Every call returns an `HttpResponse`:

```php
$response->status;               // 201
$response->body;                 // the raw body
$response->headers;              // ['location' => ['/orders/1'], ...], names lower-cased
$response->header('Location');   // '/orders/1', case-insensitive, first value or null
$response->json();               // decoded body as arrays, throws on invalid JSON
```

## AssertResponse

`AssertResponse` is a fluent assertion for HTTP responses. It accepts MicroUnit's `HttpResponse`, PSR-7 responses, Symfony responses, Laravel's `TestResponse` and CodeIgniter's `TestResponse`, so the same assertions work whether the response came from a real server or a framework's test client.

```php
use MicroUnit\Assertion\AssertResponse;

$tester->define('creating_an_order_returns_its_location', function (HttpServer $app) {
    $response = Http::post($app->url('/orders'), json: ['sku' => 'A1']);

    AssertResponse::begin($response)
        ->statusIs(201)
        ->headerIs('Location', '/orders/1')
        ->jsonPathEquals('data.sku', 'A1');
});
```

| Method | Passes when |
| --- | --- |
| `statusIs(int $expected)` | The status is exactly `$expected`. |
| `isOk()` | The status is any 2xx. |
| `isNotFound()` | The status is 404. |
| `isRedirect(?string $location = null)` | The status is 3xx, and the `Location` header equals `$location` if given. |
| `headerIs(string $name, string $expected)` | The header equals `$expected`. |
| `headerContains(string $name, string $needle)` | The header contains `$needle`. |
| `bodyContains(string $needle)` | The body contains `$needle`. |
| `bodyEquals(string $expected)` | The body is exactly `$expected`. |
| `jsonEquals(mixed $expected)` | The decoded JSON equals `$expected` (loose `==`, so key order in objects doesn't matter). |
| `jsonPathEquals(string $path, mixed $expected)` | The value at a dot path is identical (`===`) to `$expected`: `'data.items.0.id'`. |
| `jsonHasPath(string $path)` | The dot path exists in the JSON body. |

Every method takes an optional `$message` as its last argument. Failures include an excerpt of the response body, which is usually all you need to see why:

```
[✖] the_product_page_loads (1.37ms)
    Failed asserting that the response is successful (2xx).
    Response body: {"error":"not found"}
    Expected: '2xx' (string)
    Actual  : 404 (int)
```

## TemporaryDirectory

Tests that write files should write them somewhere fresh and throw it away afterwards. `TemporaryDirectory::create()` makes a uniquely named directory under the system temp directory (the optional argument is the name prefix, default `microunit`):

```php
use MicroUnit\Toolkit\TemporaryDirectory;

$tester->fixture('dir', fn() => TemporaryDirectory::create(), cleanup: fn(TemporaryDirectory $dir) => $dir->delete());

$tester->define('exports_a_csv_report', function (TemporaryDirectory $dir) {
    new ReportExporter()->export($dir->path('reports/march.csv'));

    Assert::isTrue($dir->exists('reports/march.csv'));
    Assert::stringStartsWith('date,revenue', $dir->read('reports/march.csv'));
});
```

| Method | What it does |
| --- | --- |
| `path(string $relativePath = '')` | Absolute path inside the directory (the root itself without an argument). Also available as `$dir->root`. |
| `write(string $relativePath, string $contents)` | Writes a file, creating parent directories, and returns its absolute path. |
| `read(string $relativePath)` | The file's contents; throws if it doesn't exist. |
| `exists(string $relativePath)` | Whether the file or directory exists. |
| `delete()` | Removes the directory and everything in it. |

## Assert::eventually: for things that become true later

Queues, background workers, file watchers, a server that's still warming up: some results don't exist at the moment you check. Sleeping for a fixed time makes tests slow when the system is fast and flaky when it's slow. `Assert::eventually()` retries a check until it passes or the timeout runs out, then reports the last failure:

```php
$tester->define('the_worker_sends_the_confirmation', function (Database $db) {
    dispatchOrder($db, sku: 'A1');

    $mail = Assert::eventually(function () use ($db) {
        $mail = $db->selectOne('SELECT * FROM outbox WHERE subject = ?', ['Order confirmed']);
        Assert::notNull($mail);

        return $mail;
    }, timeoutInSeconds: 3);

    Assert::stringContains('A1', $mail['body']);
});
```

The check passes when no assertion inside it fails, nothing throws, and it doesn't return `false`. `eventually()` then returns whatever the check returned. Note that returning `null` counts as passing, so assert on the value (as above) instead of just returning it.

The signature is `eventually(callable $check, float $timeoutInSeconds = 5.0, float $intervalInSeconds = 0.1, string $message = '')`. When time runs out, the failure tells you how many attempts were made and what went wrong on the last one:

```
Failed asserting that the check passes within 3 s (31 attempts). Last failure:
  Failed asserting that value is not null.
```

## Skipping when a service is missing

A test that needs Redis shouldn't fail on a laptop without Redis. It should say "skipped, here's why" and get out of the way. `Database::sqlite()` and `Database::connect()` do this for you. For everything else:

```php
use MicroUnit\Assertion\Assert;

$tester->define('caches_the_price_list', function () {
    Assert::skipIf(!extension_loaded('redis'), 'The redis extension is not installed.');
    // ...
});

$tester->define('uploads_to_s3', function () {
    if (getenv('S3_BUCKET') === false) {
        Assert::skip('S3_BUCKET is not set.');
    }
    // ...
});
```

Skips work anywhere in the chain: in the test, in `setUp()`, in a fixture factory, and in `setUpOnce()`, where they skip the whole suite. Skipped tests don't fail the run, and every report lists them with their reason.

In CI, where the services are supposed to exist, skips can hide a broken setup. Glance at the skipped list in your [reports](output.md) now and then, or provide the service so the tests actually run (see [CI/CD](ci-cd.md)).

## Keeping integration tests separate

Integration tests are slower, and you don't always want them. Put them in their own test group:

```php
return MicroUnitConfigBuilder::create()
    ->withTestGroup('unit', 'tests/Unit')
    ->withTestGroup('integration', 'tests/Integration')
    ->build();
```

`microunit --group=unit` runs the fast ones while you work, and a plain `microunit` runs everything. See [Configuration](configuration.md) and [Command line](cli.md).

## Related

- [Writing tests](writing-tests.md): `setUp()`, `tearDown()`, data sets and the rest of the basics.
- [Laravel, Symfony and CodeIgniter](frameworks.md): the framework's own testing helpers inside MicroUnit.
- [Mocking](mocking.md): when the real thing is too slow or too expensive.
- [Parallel runs](parallel.md): how suites and their shared fixtures are distributed.
