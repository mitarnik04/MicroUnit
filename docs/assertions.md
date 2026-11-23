---
layout: default
title: Assertions
description: The complete Assert reference, the two styles of writing a check, HTTP response assertions and how to write your own.
---

# Assertions

Everything you check in a MicroUnit test goes through `MicroUnit\Assertion\Assert`. It is one class with static methods, grouped here by topic: values, numbers and dates, collections, strings and JSON, files, behaviour and snapshots. The names are plain English, the argument order is the one you already know from PHPUnit, and a failure tells you what was expected, what arrived and, where it helps, shows a diff. This page lists every method with its signature, what it checks and a small example. If you have not written a test yet, start with [writing tests](writing-tests.md).

## Two styles: static and fluent

A check on a value can be written in two styles. Static calls name the check and pass the value. Fluent chains start from the value and add one check after another:

```php
Assert::notNull($user->email);
Assert::stringEndsWith('@example.com', $user->email);

AssertString::begin($user->email)->notEmpty()->endsWith('@example.com');
```

The fluent classes call the same `Assert` methods underneath, so failures, messages and assertion counting are identical. There is one class per kind of value: `AssertSingle`, `AssertNumeric`, `AssertArray`, `AssertString` and `AssertFile`. Both styles mix freely in one test. This page describes every check in its static form; [fluent assertions](fluent-assertions.md) lists every fluent method with its static twin.

## Two rules that cover every method

**Expected first, actual last, message at the very end.** It is PHPUnit's order, so muscle memory carries over:

```php
Assert::exact(42, $answer);
Assert::countEquals(3, $cart->items());
Assert::stringStartsWith('INV-', $invoice->number, 'Invoice numbers need the INV- prefix');
```

The optional `$message` is printed above the assertion's own description when it fails. It is always the last parameter, so for methods with optional parameters in between, use a named argument: `Assert::contains('2', $ids, message: 'id missing')`.

**Behaviour assertions take the callable first.** When the thing under test is "what happens when this code runs", the code comes first and the expectation follows:

```php
Assert::throws(fn () => $cart->checkout(), EmptyCartException::class);
Assert::prints(fn () => $report->render(), "Total: 3\n");
```

## Loose or strict: `equals` versus `exact`

`Assert::exact()` is `===`. Same type, same value, and for objects the same instance. It is the one to reach for by default, because it catches the `'42'` that should have been `42`.

`Assert::equals()` is loose, and smarter than PHP's `==` in one important way: objects of the same class are compared property by property, recursively, like PHPUnit's `assertEquals`. Two `Money(5, 'EUR')` objects are equal even though they are different instances. Dates compare as moments in time, so `12:00 UTC` equals `13:00 +01:00`. Objects of different classes are never equal. Scalars inside still compare with `==`, so `1` equals `'1'`.

```php
Assert::equals(new Money(5, 'EUR'), $wallet->balance()); // passes for an equal copy
Assert::exact(new Money(5, 'EUR'), $wallet->balance());  // fails: different instance
Assert::equals(1, '1');                                  // passes
Assert::exact(1, '1');                                   // fails
```

My rule of thumb: `exact` for scalars and anything where the type matters, `equals` for value objects and arrays of them.

## What a failure looks like

Every failed assertion throws a `TestFailedException` carrying an `AssertionFailure`: a description, the expected and actual values, an optional diff and your custom message. Value comparisons (`equals`, `exact`, `equivalent`, `containsSubset`, `keysEqual`, the string equality checks, `matchesFormat`, `jsonEquals`, `fileContentEquals`, `prints` and snapshots) render a diff, so a two-hundred-line array points straight at the line that changed:

```text
[✖] stores_roles (0.07ms)
    roles changed
    Failed asserting that two values are equal.
    --- Expected
    +++ Actual
    @@ @@
          'name' => 'Ada' (string),
          'roles' => [
              0 => 'admin' (string),
    -         1 => 'dev' (string),
    +         1 => 'ops' (string),
          ],
      ]
```

The rest print `Expected:` and `Actual:` lines. Values always carry their type (`'42' (string)` versus `42 (int)`), because "expected 42, got 42" is the most useless failure message in testing. How results are rendered for terminals, CI and AI agents is covered in [output](output.md).

## Every assertion counts

Each assertion that runs, passing or failing, is counted for the current test: every `Assert` check once (`throws()`, `fileContains()` and a passing `eventually()` included, plus whatever the blocks of `each()`, `multiple()` and `eventually()` assert inside) and every `AssertMock` or `AssertResponse` check once, so `AssertMock::begin($mock)->isCalledOnce('send')->isCalledWith('send', [$mail])` counts two. A test that passes without making a single assertion is flagged as risky: the console prints `no assertions` next to it, and agent output lists it as a warning. A test that cannot fail is not a test, and this is the cheapest way to catch the one where the assertion sits behind an `if` that never runs.

To make such tests fail the run, enable `failOnRiskyTests()` on the [configuration](configuration.md) builder.

## Values

### `equals` / `notEquals`

```php
Assert::equals(mixed $expected, mixed $actual, string $message = ''): void
Assert::notEquals(mixed $unexpected, mixed $actual, string $message = ''): void
```

Loose comparison, objects of the same class property by property (see above).

```php
Assert::equals(['id' => 1, 'name' => 'Ada'], $user->toArray());
Assert::notEquals($before, $after);
```

### `exact` / `notExact`

```php
Assert::exact(mixed $expected, mixed $actual, string $message = ''): void
Assert::notExact(mixed $unexpected, mixed $actual, string $message = ''): void
```

Strict comparison (`===`).

```php
Assert::exact(3, $cart->count());
Assert::notExact($original, $clone); // a different instance
```

### `isTrue` / `isFalse` / `isNull` / `notNull`

```php
Assert::isTrue(mixed $actual, string $message = ''): void
Assert::isFalse(mixed $actual, string $message = ''): void
Assert::isNull(mixed $actual, string $message = ''): void
Assert::notNull(mixed $actual, string $message = ''): void
```

Strict checks: `isTrue(1)` fails, only `true` passes.

```php
Assert::isTrue($user->isActive());
Assert::notNull($repository->find(42));
```

### `instanceOf`

```php
Assert::instanceOf(string $expectedClass, mixed $actual, string $message = ''): void
```

The value is an instance of the class or interface. The failure shows the actual type.

```php
Assert::instanceOf(OrderPlaced::class, $events[0]);
```

### `isType`

```php
Assert::isType(string $expectedType, mixed $actual, string $message = ''): void
```

Checks a type written the way PHP writes types: `int`, `?string`, `int|float`, a class, interface or enum name, plus a few extras: `list`, `numeric`, `scalar`, `callable`, `iterable`, `resource`, `mixed`. An unknown type name throws an `InvalidArgumentException` instead of silently passing.

```php
Assert::isType('?string', $user->nickname);
Assert::isType('int|float', $metrics['latency']);
Assert::isType('list', $response['items']);
```

PHPUnit has one method per type (`assertIsInt`, `assertIsString`, ...) and no way to say "nullable" or "int or float" in one call.

### `equivalent`

```php
Assert::equivalent(mixed $expected, mixed $actual, string $message = ''): void
```

Same contents, order ignored, recursively: lists are compared as multisets, maps by key. Values inside compare like `equals()`. Use it for "the same items in any order", which is what most query results and set-like collections promise.

```php
Assert::equivalent(['admin', 'dev'], $user->roles());
Assert::equivalent(['b' => [2, 1], 'a' => 1], ['a' => 1, 'b' => [1, 2]]); // passes
```

### `containsSubset`

```php
Assert::containsSubset(array|object $subset, mixed $actual, string $message = ''): void
```

`$actual` contains at least the keys of `$subset` with equal values, recursively. Everything else in `$actual` is ignored. Works on arrays, `stdClass` objects (decoded JSON), `ArrayAccess` objects and public properties. The failure names the missing paths and diffs only the part you asked about.

```php
Assert::containsSubset(['user' => ['name' => 'Ada']], $payload);
Assert::containsSubset(['status' => 'paid'], json_decode($body));
```

```text
Failed asserting that the value contains the expected subset; missing: user.email.
```

PHPUnit removed `assertArraySubset()` in version 9. This is the replacement people kept asking for, and it is the right tool for API responses that carry timestamps and ids you do not care about.

### `hasProperty`

```php
Assert::hasProperty(string $property, object $actual, string $message = ''): void
```

The object has the property (declared or dynamic, any visibility).

```php
Assert::hasProperty('email', $dto);
```

## Numbers and dates

### Comparisons

```php
Assert::isGreaterThan(int|float $minimum, int|float $actual, string $message = ''): void
Assert::isGreaterThanOrEqual(int|float $minimum, int|float $actual, string $message = ''): void
Assert::isLessThan(int|float $maximum, int|float $actual, string $message = ''): void
Assert::isLessThanOrEqual(int|float $maximum, int|float $actual, string $message = ''): void
```

`isGreaterThan` and `isGreaterThanOrEqual` take a minimum, `isLessThan` and `isLessThanOrEqual` a maximum. The bound comes first, like an expected value.

```php
Assert::isGreaterThan(0, $order->total());
Assert::isLessThanOrEqual(100, $progress);
```

### `isBetween`

```php
Assert::isBetween(int|float $minimum, int|float $maximum, int|float $actual, bool $inclusive = true, string $message = ''): void
```

Inside the range, bounds included unless `inclusive: false`.

```php
Assert::isBetween(1, 10, $roll);
Assert::isBetween(0, 1, $probability, inclusive: false);
```

### `equalsWithDelta`

```php
Assert::equalsWithDelta(int|float $expected, int|float $actual, int|float $delta, string $message = ''): void
```

Absolute tolerance. The answer to `0.1 + 0.2 !== 0.3`.

```php
Assert::equalsWithDelta(0.3, 0.1 + 0.2, 0.0001);
```

### `equalsWithinPercent`

```php
Assert::equalsWithinPercent(int|float $expected, int|float $actual, int|float $percent, string $message = ''): void
```

Relative tolerance: `equalsWithinPercent(100, $measured, 5)` accepts 95 to 105. Better than a delta whenever the scale of the number varies, for timings, sizes and estimates.

```php
Assert::equalsWithinPercent(1_000_000, $estimatedRows, 10);
```

### `isNan` / `isFinite`

```php
Assert::isNan(mixed $actual, string $message = ''): void
Assert::isFinite(int|float $actual, string $message = ''): void
```

`isNan` passes only for the float `NAN`. `isFinite` fails for `NAN`, `INF` and `-INF`.

```php
Assert::isFinite($calculator->ratio($a, $b));
```

### `dateEquals`

```php
Assert::dateEquals(\DateTimeInterface|string $expected, \DateTimeInterface|string $actual, int|float $toleranceInSeconds = 0, string $message = ''): void
```

Same moment in time, give or take the tolerance. Strings are parsed like `new DateTimeImmutable()`, so `'now'`, `'+1 day'` and ISO dates all work, and time zones are taken into account. The failure says how far apart the two dates were.

```php
Assert::dateEquals('now', $user->createdAt, toleranceInSeconds: 5);
Assert::dateEquals('2026-01-01 12:00 UTC', '2026-01-01 13:00 +01:00'); // passes
```

```text
Failed asserting that the dates are equal within 5 s (they differ by 9 s).
```

This is the assertion I wanted for years. "Created just now" is one of the most common checks there is, and doing it with timestamps and a delta is noise.

## Collections

These accept arrays, and where the signature says `iterable` also generators and other `Traversable` objects.

### `empty` / `notEmpty`

```php
Assert::empty(mixed $actual, string $message = ''): void
Assert::notEmpty(mixed $actual, string $message = ''): void
```

Empty means: an array or `Countable` without elements, `''`, `null`, `0` or `false`. Unlike PHP's `empty()`, the string `'0'` is **not** empty.

```php
Assert::empty($validator->errors());
Assert::notEmpty($search->results());
```

### `contains` / `notContains`

```php
Assert::contains(mixed $needle, iterable $haystack, bool $strict = true, string $message = ''): void
Assert::notContains(mixed $needle, iterable $haystack, bool $strict = true, string $message = ''): void
```

The collection has (or lacks) the element. Strict by default; pass `strict: false` for `==`. For substrings, use [`stringContains`](#stringcontains--stringnotcontains).

```php
Assert::contains('admin', $user->roles());
Assert::contains('2', [1, 2, 3], strict: false);
```

### `containsAll`

```php
Assert::containsAll(array $expected, iterable $actual, bool $strict = true, string $message = ''): void
```

Every element of `$expected` is in `$actual`, in any order. Other elements are allowed.

```php
Assert::containsAll(['admin', 'dev'], $user->roles());
```

### `containsOnly`

```php
Assert::containsOnly(array $allowedValues, array $actual, string $message = ''): void
```

Every element of `$actual` is one of the allowed values (strict). The failure lists the offenders.

```php
Assert::containsOnly(['draft', 'published'], array_column($posts, 'status'));
```

### `countEquals` / `sameSize`

```php
Assert::countEquals(int $expected, array|\Countable $actual, string $message = ''): void
Assert::sameSize(array|\Countable $expected, array|\Countable $actual, string $message = ''): void
```

```php
Assert::countEquals(3, $cart->items());
Assert::sameSize($input, $output);
```

### `hasKey` / `notHasKey`

```php
Assert::hasKey(int|string $key, array $actual, string $message = ''): void
Assert::notHasKey(int|string $key, array $actual, string $message = ''): void
```

```php
Assert::hasKey('id', $row);
Assert::notHasKey('password', $user->toArray());
```

### `keysEqual`

```php
Assert::keysEqual(array $expectedKeys, array $actual, string $message = ''): void
```

The array has exactly these keys, in any order. No more, no fewer.

```php
Assert::keysEqual(['id', 'name', 'email'], $user->toArray());
```

### `isList`

```php
Assert::isList(mixed $actual, string $message = ''): void
```

An array with keys `0, 1, 2, ...` in order. Catches the `array_filter()` result you forgot to `array_values()` before `json_encode()` turned it into an object.

```php
Assert::isList($response['items']);
```

### `each`

```php
Assert::each(iterable $items, callable $check, string $message = ''): void
```

Runs `$check($item, $key)` for every element. The check fails an element by making a failing assertion or by returning `false`. Every failing element is reported with its key, not only the first one (up to ten are listed in full).

```php
Assert::each($users, fn (User $user) => Assert::isTrue($user->isActive()));
Assert::each($prices, fn (int $price) => $price > 0);
```

```text
Failed asserting that every element passes; 2 of 4 failed:
  [1] 3 is odd
    Failed asserting that two values are identical (type and value).
  [3] 5 is odd
    Failed asserting that two values are identical (type and value).
```

PHPUnit has `assertContainsOnly()` for types and a `foreach` loop for everything else, which stops at the first bad element.

### `isUnique`

```php
Assert::isUnique(iterable $actual, string $message = ''): void
```

No element appears twice. Strict, so `1` and `'1'` are different; objects are compared by identity. The failure lists the duplicates.

```php
Assert::isUnique(array_column($orders, 'number'));
```

### `isSorted`

```php
Assert::isSorted(iterable $actual, ?callable $by = null, bool $descending = false, string $message = ''): void
```

Elements are in ascending order (or descending), compared with `<=>`. Equal neighbours are fine. Pass `by:` to sort by a derived value. The failure names the first element that is out of order.

```php
Assert::isSorted($leaderboard->scores(), descending: true);
Assert::isSorted($posts, by: fn (Post $post) => $post->publishedAt, descending: true);
```

## Strings and JSON

### `stringContains` / `stringNotContains`

```php
Assert::stringContains(string $needle, string $haystack, string $message = ''): void
Assert::stringNotContains(string $needle, string $haystack, string $message = ''): void
```

```php
Assert::stringContains('Welcome, Ada', $html);
Assert::stringNotContains('Exception', $log);
```

### `stringContainsIgnoringCase`

```php
Assert::stringContainsIgnoringCase(string $needle, string $haystack, string $message = ''): void
```

Case-insensitive, multibyte-aware when `mbstring` is available.

```php
Assert::stringContainsIgnoringCase('error', $output);
```

### `stringStartsWith` / `stringEndsWith`

```php
Assert::stringStartsWith(string $prefix, string $actual, string $message = ''): void
Assert::stringEndsWith(string $suffix, string $actual, string $message = ''): void
```

```php
Assert::stringStartsWith('INV-', $invoice->number);
Assert::stringEndsWith('.pdf', $attachment->filename);
```

### `matchesRegex`

```php
Assert::matchesRegex(string $pattern, string $actual, string $message = ''): void
```

An invalid pattern throws an `InvalidArgumentException` instead of failing quietly.

```php
Assert::matchesRegex('/^\d{4}-\d{2}-\d{2}$/', $date);
```

### `matchesFormat`

```php
Assert::matchesFormat(string $format, string $actual, string $message = ''): void
```

Matches text with placeholders, the format PHP's own `.phpt` tests use (PHPUnit's `assertStringMatchesFormat()` speaks the same language). Much easier to read than a regex when output contains timestamps, paths or ids. Line endings are normalised, and a failure is shown as a line diff.

| Placeholder | Matches |
| --- | --- |
| `%s` | one or more characters on one line |
| `%S` | the same, or nothing |
| `%a` | one or more characters, across lines |
| `%A` | the same, or nothing |
| `%d` | digits |
| `%i` | a signed integer |
| `%f` | a float |
| `%x` | hexadecimal digits |
| `%c` | a single character |
| `%w` | optional whitespace |
| `%e` | the directory separator |
| `%%` | a literal `%` |

```php
Assert::matchesFormat("Saved order %d at %s\n", $output);
Assert::matchesFormat("id: %x, took %fms%A", $log);
```

### `equalsIgnoringCase`

```php
Assert::equalsIgnoringCase(string $expected, string $actual, string $message = ''): void
```

```php
Assert::equalsIgnoringCase('Ada Lovelace', $user->name);
```

### `equalsIgnoringWhitespace`

```php
Assert::equalsIgnoringWhitespace(string $expected, string $actual, string $message = ''): void
```

Equal after trimming both strings and collapsing every run of whitespace (spaces, tabs, newlines) into one space. Made for SQL, HTML and generated code where the indentation is not what you are testing. Note that whitespace is collapsed, not removed: `<ul><li>` and `<ul> <li>` are still different.

```php
Assert::equalsIgnoringWhitespace('SELECT * FROM users WHERE id = 1', $query->toSql());
```

PHPUnit has no equivalent; you end up writing the `preg_replace` yourself.

### `isJson`

```php
Assert::isJson(string $actual, string $message = ''): void
```

The string is valid JSON. The failure includes the parser's error.

```php
Assert::isJson($response->body);
```

### `jsonEquals`

```php
Assert::jsonEquals(mixed $expected, string $actualJson, string $message = ''): void
```

Same JSON document. Object key order and formatting are ignored; list order and types are not, so `1`, `1.0` and `"1"` are three different values. `$expected` can be a JSON string or the PHP value it should decode to, which saves you from writing JSON inside PHP strings. The failure is a diff of both documents, pretty-printed with sorted keys.

```php
Assert::jsonEquals(['id' => 7, 'tags' => ['a', 'b']], $response->body);
Assert::jsonEquals('{"b": 2, "a": 1}', '{"a":1,"b":2}'); // passes
```

## Files

```php
Assert::fileExists(string $path, string $message = ''): void
Assert::fileNotExists(string $path, string $message = ''): void
Assert::directoryExists(string $path, string $message = ''): void
Assert::fileContains(string $needle, string $path, string $message = ''): void
Assert::fileContentEquals(string $expected, string $path, string $message = ''): void
```

`fileExists` wants a regular file, `directoryExists` a directory; `fileNotExists` fails if anything exists at the path. `fileContains` and `fileContentEquals` first check that the file exists and is readable. `fileContentEquals` normalises line endings on both sides, so a test written on Linux passes against a file generated on Windows, and shows a line diff on failure.

```php
$exporter->export($dir);

Assert::fileExists("$dir/report.csv");
Assert::fileContains('Total;3', "$dir/report.csv");
Assert::fileContentEquals("Total: 3\nDone\n", "$dir/summary.txt");
```

Pair them with `TemporaryDirectory` from the [integration testing](integration-testing.md) toolkit, so every test gets a clean directory that is deleted afterwards.

## Behaviour

### `throws`

```php
Assert::throws(callable $callable, ?string $expectedType = null, ?string $expectedMessage = null, string $message = ''): \Throwable
```

The callable throws. Optionally of a type (subclasses count) and with a message that contains `$expectedMessage`. It returns the caught exception, so you can assert on its details right after. No `expectException()` that has to come before the code and silently swallows the rest of the test.

```php
$exception = Assert::throws(fn () => $stock->reserve('A1', 5), OutOfStock::class, 'out of stock');
Assert::exact('A1', $exception->sku);
```

```text
Failed asserting that the thrown exception is of type OutOfStock.
Expected: 'OutOfStock' (string)
Actual  : 'LogicException: nope' (string)
```

### `doesNotThrow`

```php
Assert::doesNotThrow(callable $callable, string $message = ''): mixed
```

Runs the callable and returns its result. If it throws, the test fails with a clear "no exception expected" message that includes the exception's class, message, file and line, instead of ending as an error. It also counts as an assertion, so "this does not blow up" is a legitimate, non-risky test.

```php
$order = Assert::doesNotThrow(fn () => $importer->import($legacyCsv));
Assert::exact(120, $order->lineCount());
```

### `triggersError`

```php
Assert::triggersError(callable $callable, int $level = E_ALL, ?string $expectedMessage = null, string $message = ''): string
```

The callable triggers a PHP warning, notice or deprecation of `$level`, optionally with a message containing `$expectedMessage`. It returns the message. The error is captured, so it neither prints nor reaches other error handlers.

```php
Assert::triggersError(fn () => $legacy->run(), E_USER_DEPRECATED, 'use runNew() instead');
```

PHPUnit 10 removed `expectWarning()`, `expectDeprecation()` and friends. Deprecations are exactly what you want to test when you maintain a library, so this one stays.

### `prints`

```php
Assert::prints(callable $callable, string $expected, string $message = ''): void
```

The callable prints exactly `$expected` (via `echo`, `print`, `printf` and so on). Scoped to the callable, unlike PHPUnit's `expectOutputString()`, which applies to the whole test.

```php
Assert::prints(fn () => $command->run(['--dry-run']), "Would delete 3 files\n");
```

### `eventually`

```php
Assert::eventually(callable $check, float $timeoutInSeconds = 5.0, float $intervalInSeconds = 0.1, string $message = ''): mixed
```

Retries `$check` until it passes (no failed assertion, no exception, not `false`) or the timeout runs out, then reports the last failure and how many attempts were made. It returns whatever the check returned. For queues, background workers, servers, file watchers and anything else that becomes true a moment later. Much better than a `sleep(2)` that is too long on your machine and too short on CI.

```php
Assert::eventually(fn () => Assert::notNull($mailbox->latest()), timeoutInSeconds: 3);

$job = Assert::eventually(function () use ($queue, $id) {
    $job = $queue->find($id);
    Assert::isTrue($job?->isDone());

    return $job;
});
```

Assertions return nothing, so when you need the value afterwards, return it from the closure yourself, as in the second example.

```text
Failed asserting that the check passes within 0.3 s (4 attempts). Last failure:
  Failed asserting that two values are identical (type and value).
```

### `multiple`: soft assertions

```php
Assert::multiple(callable $assertions, string $message = ''): void
```

Runs every `Assert` call in the block even when some fail, then reports all failures together. One run shows everything that is wrong with a result, instead of fix, rerun, fix, rerun.

```php
Assert::multiple(function () use ($user) {
    Assert::exact('Ada', $user->name);
    Assert::exact('ada@example.com', $user->email);
    Assert::isTrue($user->active);
});
```

```text
2 assertions failed:
1) Failed asserting that two values are identical (type and value).
   Expected: 'Ada' (string)
   Actual  : 'Grace' (string)
2) Failed asserting that value is true.
   Expected: true (bool)
   Actual  : false (bool)
```

Blocks nest, and `each()` and `eventually()` work inside them. `Assert`, `AssertMock` and `AssertResponse` checks are all collected softly. A few things end the block at their failure (which is still reported, together with the failures before it): `fail()`, `throws()` when nothing is thrown and `triggersError()` when nothing is triggered, because they cannot carry on without a result, and [custom assertions](#custom-assertions) that throw `TestFailedException` themselves.

### `skip` / `skipIf` / `fail`

```php
Assert::skip(string $reason = 'Skipped.'): never
Assert::skipIf(bool $condition, string $reason): void
Assert::fail(string $message = 'Test failed.'): never
```

Not assertions in the strict sense, but they live here. Skipped tests show up as skipped with their reason, never as passed.

```php
Assert::skipIf(!extension_loaded('pdo_pgsql'), 'pdo_pgsql missing');
Assert::fail('Not implemented yet');
```

## Snapshots

```php
Assert::matchesSnapshot(mixed $actual, ?string $name = null, string $message = ''): void
```

Compares a value with a stored copy next to the test file. The first run writes it, later runs fail on any difference, and `--update-snapshots` accepts intended changes. Built in, no plugin needed (PHPUnit needs a separate package for this).

```php
Assert::matchesSnapshot($invoice->renderHtml());
```

Where snapshots live, how CI treats them and when they are a bad idea: [snapshots](snapshots.md).

## HTTP responses: `AssertResponse`

`AssertResponse` asserts on HTTP responses fluently, whichever library produced them:

```php
AssertResponse::begin($response)
    ->statusIs(201)
    ->headerIs('Location', '/products/A1')
    ->jsonPathEquals('data.sku', 'A1');
```

`begin()` accepts:

| Response | Recognised by |
| --- | --- |
| MicroUnit's `HttpResponse` (from `Http::get()`, `Http::post()`, ...) | its class |
| PSR-7 responses (Guzzle, Nyholm, Laminas, ...) | `getStatusCode()` and `getHeaderLine()` |
| Symfony `Response` (HttpFoundation) | `getStatusCode()` and `getContent()` |
| Laravel `TestResponse` | its `baseResponse` (a Symfony response) |
| CodeIgniter `TestResponse` | its class; the wrapped response is read |

Anything else throws an `InvalidArgumentException` naming the class it got. No package is required: the types are detected by duck typing.

| Method | Checks |
| --- | --- |
| `statusIs(int $expected)` | exact status code |
| `isOk()` | any 2xx status |
| `isNotFound()` | status 404 |
| `isRedirect(?string $location = null)` | a 3xx status, and the exact `Location` header when given |
| `headerIs(string $name, string $expected)` | the header's value equals `$expected` |
| `headerContains(string $name, string $needle)` | the header exists and contains `$needle` |
| `bodyContains(string $needle)` | the body contains the text |
| `bodyEquals(string $expected)` | the body equals the text exactly |
| `jsonEquals(mixed $expected)` | the decoded JSON body equals `$expected` (loose comparison, object key order irrelevant) |
| `jsonPathEquals(string $path, mixed $expected)` | the value at a dot path equals `$expected` strictly: `'data.items.0.id'` |
| `jsonHasPath(string $path)` | the dot path exists in the JSON body |

Every method takes an optional `$message` as last argument. Header names are case-insensitive. A body that is not valid JSON fails the JSON methods with a clear message rather than a `JsonException`. Every failure includes the start of the response body, because "expected 200, got 500" is only half the story:

```text
Failed asserting that the response is successful (2xx).
Response body: {"error":"boom"}
Expected: '2xx' (string)
Actual  : 500 (int)
```

Mind the difference between the two JSON checks: `jsonEquals()` compares loosely, `jsonPathEquals()` strictly, so `jsonPathEquals('data.id', 42)` fails when the API returns `"42"`. For more on testing applications over HTTP, see [integration testing](integration-testing.md) and [frameworks](frameworks.md).

## Custom assertions

A custom assertion is a function that throws `MicroUnit\Exceptions\TestFailedException` with a `MicroUnit\Assertion\AssertionFailure` when the check fails. Before you write one, see if it can be built from existing assertions; then you get the messages, diffs and counting for free:

```php
function assertValidIban(string $iban, string $message = ''): void
{
    Assert::matchesRegex('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban, $message);
}
```

When you need your own failure, build it yourself:

```php
use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Core\TestContext;
use MicroUnit\Exceptions\TestFailedException;

function assertIsEven(int $actual, string $message = ''): void
{
    TestContext::countAssertions();
    if ($actual % 2 !== 0) {
        throw new TestFailedException(new AssertionFailure(
            'Failed asserting that the number is even.',
            expected: 'an even number',
            actual: $actual,
            customMessage: $message,
        ));
    }
}
```

`AssertionFailure` takes the description, the expected and actual values (both optional: leave them out and no `Expected:` / `Actual:` lines are printed), an optional `MicroUnit\Helpers\Diff` (`Diff::generate($expected, $actual)` for values, `Diff::fromLines($expectedLines, $actualLines)` for text) and your custom message. `TestContext::countAssertions()` tells MicroUnit an assertion ran, so a test that only uses your assertion is not reported as risky.

One limitation: a custom assertion that throws directly ends an [`Assert::multiple()`](#multiple-soft-assertions) block at its failure instead of being collected with the others.

Failures from PHPUnit assertions (Laravel's and Symfony's test helpers assert through PHPUnit when it is installed) are translated into MicroUnit failures automatically, diff included. See [frameworks](frameworks.md).

## Coming from PHPUnit

Most assertions map one to one; `microunit migrate` does the translation for you (see [migrating from PHPUnit](migrating-from-phpunit.md)). These are the ones PHPUnit does not have or makes awkward:

| MicroUnit | In PHPUnit |
| --- | --- |
| `equivalent` | `assertEqualsCanonicalizing` comes closest |
| `containsSubset` | `assertArraySubset` was removed in PHPUnit 9 |
| `isType('?int')`, `isType('int\|float')` | one method per type, no nullable or union types |
| `each` | a `foreach` that stops at the first failure |
| `isUnique`, `isSorted` | write it yourself |
| `equalsWithinPercent` | only absolute deltas |
| `dateEquals` | compare timestamps with a delta |
| `equalsIgnoringWhitespace` | normalise both strings yourself |
| `jsonEquals` with a PHP array | expects two JSON strings |
| `fileContentEquals` | compares raw bytes, line endings included |
| `throws` returning the exception | `expectException()` before the code, no access to the exception |
| `doesNotThrow` | call the code and `expectNotToPerformAssertions()` |
| `triggersError` | `expectWarning()` and friends were removed in PHPUnit 10 |
| `prints` | `expectOutputString()` applies to the whole test |
| `eventually` | `sleep()` and hope |
| `multiple` | no soft assertions |
| `matchesSnapshot` | needs a third-party package |
