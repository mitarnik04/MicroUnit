---
layout: default
title: Fluent assertions
description: Start from the value and chain the checks, with AssertSingle, AssertNumeric, AssertArray, AssertString and AssertFile. Every method, what it checks and its static twin.
---

# Fluent assertions

Every check on a value can be written in two styles. The static style names the check and passes the value to it. The fluent style starts from the value and chains the checks:

```php
// Static
Assert::notEmpty($slug);
Assert::stringStartsWith('post-', $slug);
Assert::matchesRegex('/^[a-z0-9-]+$/', $slug);

// Fluent
AssertString::begin($slug)
    ->notEmpty()
    ->startsWith('post-')
    ->matchesRegex('/^[a-z0-9-]+$/');
```

Both run the same code underneath. A fluent method calls its `Assert` twin, so the failure message, the diff and the assertion count are identical, and so is everything the [assertions reference](assertions.md) says about a check. Use the style that reads better to you. Both mix freely in one test, and a suite can settle on either.

## How it works

- **One class per kind of value.** `begin($actual)` takes the value, and every method returns the same object, so the next check follows with `->`. Because each class only offers the checks that make sense for its type, your editor's autocompletion lists exactly those.
- **The same argument order.** The expected value comes first and the optional `$message` last, as on `Assert`. The actual value is the one you passed to `begin()`.
- **A chain stops at its first failure**, like a test stops at a failing `Assert` call. Inside [`Assert::multiple()`](assertions.md#multiple-soft-assertions) a failing check is recorded and the chain goes on, so you see every failure at once.

| Class | For | Example |
| --- | --- | --- |
| `AssertSingle` | any value | `AssertSingle::begin($user)->instanceOf(Admin::class)` |
| `AssertNumeric` | `int` and `float` | `AssertNumeric::begin($total)->isBetween(1, 100)` |
| `AssertArray` | arrays | `AssertArray::begin($row)->hasKey('id')->notHasKey('password')` |
| `AssertString` | strings | `AssertString::begin($html)->contains('<h1>')` |
| `AssertFile` | a path on disk | `AssertFile::begin($path)->exists()->contains('total')` |
| `AssertResponse` | HTTP responses | see [HTTP responses](assertions.md#http-responses-assertresponse) |
| `AssertMock` | mocks and spies | see [mocking](mocking.md) |

All of them live in `MicroUnit\Assertion`. The sections below list every method of the first five with its static twin.

## `AssertSingle`: any value

```php
AssertSingle::begin($user->id)->notNull()->isType('int')->exact(42);
```

| Method | Passes when the value | Static twin |
| --- | --- | --- |
| `equals($expected)` | is loosely equal (objects property by property) | `Assert::equals()` |
| `notEquals($unexpected)` | is not loosely equal | `Assert::notEquals()` |
| `exact($expected)` | is identical (`===`) | `Assert::exact()` |
| `notExact($unexpected)` | is not identical | `Assert::notExact()` |
| `instanceOf($class)` | is an instance of the class or interface | `Assert::instanceOf()` |
| `isType($type)` | has the type, written as PHP writes it: `'int'`, `'?string'`, `'list'`, a class name | `Assert::isType()` |
| `isTrue()` / `isFalse()` | is `true` / `false` | `Assert::isTrue()` / `Assert::isFalse()` |
| `isNull()` / `notNull()` | is / is not `null` | `Assert::isNull()` / `Assert::notNull()` |
| `equivalent($expected)` | has the same contents, in any order | `Assert::equivalent()` |
| `hasProperty($name)` | is an object with this property | `Assert::hasProperty()` |
| `dateEquals($expected, $toleranceInSeconds = 0)` | is the same moment in time | `Assert::dateEquals()` |
| `matchesSnapshot($name = null)` | matches the stored [snapshot](snapshots.md) | `Assert::matchesSnapshot()` |

`AssertSingle` takes any value, so `hasProperty()` and `dateEquals()` first make sure the value is something they can inspect: an object, or a date (`DateTimeInterface` or a string). Anything else fails with the type that arrived.

## `AssertNumeric`: integers and floats

```php
AssertNumeric::begin($discount)->isFinite()->isGreaterThan(0)->isLessThanOrEqual(50);
```

| Method | Passes when the number | Static twin |
| --- | --- | --- |
| `equals($expected)` | is loosely equal (`1` equals `1.0`) | `Assert::equals()` |
| `notEquals($unexpected)` | is not loosely equal | `Assert::notEquals()` |
| `exact($expected)` | is identical (`1` is not `1.0`) | `Assert::exact()` |
| `notExact($unexpected)` | is not identical | `Assert::notExact()` |
| `equalsWithDelta($expected, $delta)` | is within `$delta` of `$expected` | `Assert::equalsWithDelta()` |
| `equalsWithinPercent($expected, $percent)` | is within `$percent` % of `$expected` | `Assert::equalsWithinPercent()` |
| `isGreaterThan($minimum)` | is greater than `$minimum` | `Assert::isGreaterThan()` |
| `isGreaterThanOrEqual($minimum)` | is `$minimum` or greater | `Assert::isGreaterThanOrEqual()` |
| `isLessThan($maximum)` | is less than `$maximum` | `Assert::isLessThan()` |
| `isLessThanOrEqual($maximum)` | is `$maximum` or less | `Assert::isLessThanOrEqual()` |
| `isBetween($minimum, $maximum, $inclusive = true)` | lies in the range | `Assert::isBetween()` |
| `isNan()` | is `NAN` | `Assert::isNan()` |
| `isFinite()` | is neither `NAN` nor `±INF` | `Assert::isFinite()` |

## `AssertArray`: arrays

```php
AssertArray::begin($users)
    ->isList()
    ->countEquals(3)
    ->isSorted(by: fn(User $user) => $user->name)
    ->each(fn(User $user) => Assert::isTrue($user->isActive()));
```

| Method | Passes when the array | Static twin |
| --- | --- | --- |
| `equals($expected)` | is loosely equal | `Assert::equals()` |
| `notEquals($unexpected)` | is not loosely equal | `Assert::notEquals()` |
| `exact($expected)` | is identical: same keys, order, types and values | `Assert::exact()` |
| `notExact($unexpected)` | is not identical | `Assert::notExact()` |
| `empty()` / `notEmpty()` | has no elements / at least one | `Assert::empty()` / `Assert::notEmpty()` |
| `countEquals($expected)` | has this many elements | `Assert::countEquals()` |
| `sameSize($other)` | has as many elements as `$other` (array or `Countable`) | `Assert::sameSize()` |
| `contains($needle, $strict = true)` | contains the element | `Assert::contains()` |
| `notContains($needle, $strict = true)` | does not contain the element | `Assert::notContains()` |
| `containsAll($expected, $strict = true)` | contains every element of `$expected`, in any order | `Assert::containsAll()` |
| `containsOnly($allowedValues)` | contains nothing but the allowed values | `Assert::containsOnly()` |
| `containsSubset($subset)` | contains at least these keys with these values, recursively | `Assert::containsSubset()` |
| `equivalent($expected)` | has the same contents, in any order | `Assert::equivalent()` |
| `hasKey($key)` / `notHasKey($key)` | has / does not have the key | `Assert::hasKey()` / `Assert::notHasKey()` |
| `keysEqual($expectedKeys)` | has exactly these keys, in any order | `Assert::keysEqual()` |
| `isList()` | has the keys 0, 1, 2, ... in order | `Assert::isList()` |
| `isUnique()` | has no element twice (strict comparison) | `Assert::isUnique()` |
| `isSorted($by = null, $descending = false)` | is in order, optionally by a key | `Assert::isSorted()` |
| `each($check)` | has only elements that pass `$check`; every failing element is reported | `Assert::each()` |
| `matchesSnapshot($name = null)` | matches the stored [snapshot](snapshots.md) | `Assert::matchesSnapshot()` |

## `AssertString`: strings

```php
AssertString::begin($invoice->number)->startsWith('INV-')->matchesFormat('INV-%d')->lengthEquals(10);
```

| Method | Passes when the string | Static twin |
| --- | --- | --- |
| `equals($expected)` / `exact($expected)` | is identical | `Assert::exact()` |
| `notEquals($unexpected)` / `notExact($unexpected)` | is not identical | `Assert::notExact()` |
| `equalsIgnoringCase($expected)` | is equal, ignoring case | `Assert::equalsIgnoringCase()` |
| `equalsIgnoringWhitespace($expected)` | is equal after trimming and collapsing whitespace | `Assert::equalsIgnoringWhitespace()` |
| `contains($needle)` | contains the text | `Assert::stringContains()` |
| `notContains($needle)` | does not contain the text | `Assert::stringNotContains()` |
| `containsIgnoringCase($needle)` | contains the text, ignoring case | `Assert::stringContainsIgnoringCase()` |
| `startsWith($prefix)` | starts with the prefix | `Assert::stringStartsWith()` |
| `endsWith($suffix)` | ends with the suffix | `Assert::stringEndsWith()` |
| `matchesRegex($pattern)` | matches the regular expression | `Assert::matchesRegex()` |
| `matchesFormat($format)` | matches a format with placeholders such as `%d` and `%s` | `Assert::matchesFormat()` |
| `lengthEquals($expected)` | is this many characters long | no static twin |
| `empty()` / `notEmpty()` | is `''` / is not | `Assert::empty()` / `Assert::notEmpty()` |
| `isJson()` | is valid JSON | `Assert::isJson()` |
| `jsonEquals($expected)` | is the same JSON document, key order and formatting aside | `Assert::jsonEquals()` |
| `matchesSnapshot($name = null)` | matches the stored [snapshot](snapshots.md) | `Assert::matchesSnapshot()` |

Two strings are the same text or they are not, so `AssertString` compares strictly everywhere: `equals('1')` fails for `'1.0'`, which `Assert::equals()` would let pass. `lengthEquals()` counts characters, not bytes, so `'grüß'` is 4 long, with or without the `mbstring` extension.

## `AssertFile`: a path on disk

```php
AssertFile::begin($exportPath)->exists()->contains('order_id,total')->contentEquals($expectedCsv);
```

| Method | Passes when the path | Static twin |
| --- | --- | --- |
| `exists()` | is an existing file | `Assert::fileExists()` |
| `notExists()` | does not exist | `Assert::fileNotExists()` |
| `isDirectory()` | is an existing directory | `Assert::directoryExists()` |
| `contains($needle)` | is a file that contains the text | `Assert::fileContains()` |
| `contentEquals($expected)` | is a file with exactly this content (line endings normalized) | `Assert::fileContentEquals()` |

## Checks that stay on `Assert`

Some checks do not start from a value, so they keep their one form on `Assert`: the ones about code that runs (`throws`, `doesNotThrow`, `prints`, `triggersError`, `eventually`), [`multiple`](assertions.md#multiple-soft-assertions) for collecting failures, and `fail`, `skip` and `skipIf` for steering the test. They sit happily next to a fluent chain:

```php
$tester->define('exports_the_paid_orders', function (Exporter $exporter, string $path): void {
    Assert::doesNotThrow(fn() => $exporter->export($path));

    AssertFile::begin($path)->exists()->contains('order_id,total');
});
```

## Related

- [Assertions](assertions.md): every check in detail, with what a failure looks like
- [Snapshots](snapshots.md): where `matchesSnapshot()` stores its files
- [Custom assertions](assertions.md#custom-assertions): build your own checks from the existing ones
