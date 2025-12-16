---
layout: default
---

**[← Back to Home](index.md)**

# Assertions

MicroUnit provides several assertion classes.  
Each assertion method is listed below with its signature, a concise explanation, and usage examples.  
Most assertion methods can be called either via an assertion chain (e.g., `AssertSingle::begin($value)->equals(...)`) or statically (e.g., `Assert::equals($value, $expected)`).  
**Note:** Static calls are not available for `AssertMock` methods due to additional setup required for mock tracking.

---

## Table of Contents

- **[Static Assert Methods](#static-assert-methods)**
- **[Fluent Assertions](#fluent-assertions)**
  - **[AssertSingle](#assertsingle)**
  - **[AssertNumeric](#assertnumeric)**
  - **[AssertArray](#assertarray)**
- **[AssertMock](#assertmock)**
- **[AssertMockMethod](#assertmockmethod)**

---

## Static Assert Methods

Most assertion methods can be called statically via the `Assert` class.
These static methods get used under the hood by AssertSingle, AssertArray and AssertNumeric (Fluent API).

### List of Available Static Methods

- `Assert::equals(mixed $expected, mixed $actual): void`  
  Asserts that `$expected == $actual`.

- `Assert::notEquals(mixed $unexpected, mixed $actual): void`  
  Asserts that `$unexpected != $actual`.

- `Assert::exact(mixed $expected, mixed $actual): void`  
  Asserts that `$expected === $actual`.

- `Assert::notExact(mixed $unexpected, mixed $actual): void`  
  Asserts that `$unexpected !== $actual`.

- `Assert::isTrue(mixed $value): void`  
  Asserts that `$value === true`.

- `Assert::isFalse(mixed $value): void`  
  Asserts that `$value === false`.

- `Assert::isNull(mixed $value): void`  
  Asserts that `$value === null`.

- `Assert::notNull(mixed $value): void`  
  Asserts that `$value !== null`.

- `Assert::isGreaterThan(int|float $value, int|float $min): void`  
  Asserts that `$value > $min`.

- `Assert::isLessThan(int|float  $value, int|float  $max): void`  
  Asserts that `$value < $max`.

- `Assert::isBetween(int | float  $min, int|float  $max, int|float  $value, bool $inclusive = true): void`  
  Asserts that `$value` is between `$min` and `$max`. Inclusive by default.

- `Assert::empty(array $array): void`  
  Asserts that the array is empty.

- `Assert::notEmpty(array $array): void`  
  Asserts that the array is not empty.

- `Assert::contains(mixed $element, array $source, bool $shouldUseStrict = true): void`  
  Asserts that `$element` exists in `$source`. If `shouldUseStrict` is true it will also make sure the type of `$element` matches the given element in `$source`.

- `Assert::countEquals(int $expected, array|\Countable $source): void`  
  Asserts that the array contains exactly `$count` elements.

- `Assert::hasKey(mixed $key, array $array): void`  
  Asserts that the array has the specified key.

- `Assert::notHasKey(mixed $key, array $array): void`  
  Asserts that the array does not have the specified key.

- `Assert::keysEqual(array $expectedKeys, array $array): void`  
  Asserts that the keys of `$array` match `$expectedKeys` in order and value.

- `Assert::containsOnly(array $allowedValues, array $array): void`  
  Asserts that all elements in `$array` are among `$allowedValues`.

- `Assert::instanceOf(string $expectedInstance, object $object): void`  
   Asserts that `$value` is an instance of the given class or interface.

- `Assert::throws(callable $method, ?string $exceptionType): void`  
  Asserts that executing `$fn` throws an exception of type `$exceptionClass`.

> **Note:**  
> Static assert methods are **not available** for `AssertMock` because mock assertions require additional context and tracking.

## Fluent Assertions

No matter which fluent assertion class you are using to start the fluent assertion process you call `AssertClass::begin($value)` (e.g `AssertSingle::begin('something')`).

### AssertSingle

Defines different assertion methods for single values (bool, string, int, float, etc.).

### `equals(mixed $expected): self`

Asserts that the value is equal to `$expected` (==).

**Example:**

```php
AssertSingle::begin($value)->equals(42);
```

### `notEquals(mixed $unexpected): self`

Asserts that the value is not equal to `$unexpected` (==).

**Example:**

```php
AssertSingle::begin($value)->notEquals(0);
```

### `exact(mixed $expected): self`

Asserts that the value is exactly equal to `$expected` (===).

**Example:**

```php
AssertSingle::begin($value)->exact('foo');
```

### `notExact(mixed $unexpected): self`

Asserts that the value is not exactly equal to `$unexpected` (===).

**Example:**

```php
AssertSingle::begin($value)->notExact(false);
```

### `instanceOf(string $expectedInstance): self`

Asserts that the value is an instance of the given class/interface.

**Example:**

```php
AssertSingle::begin($object)->instanceOf(DateTime::class);
```

### `isTrue(): self`

Asserts that the value is `true`.

**Example:**

```php
AssertSingle::begin($flag)->isTrue();
```

### `isFalse(): self`

Asserts that the value is `false`.

**Example:**

```php
AssertSingle::begin($flag)->isFalse();
```

### `isNull(): self`

Asserts that the value is `null`.

**Example:**

```php
AssertSingle::begin($value)->isNull();
```

### `notNull(): self`

Asserts that the value is not `null`.

**Example:**

```php
AssertSingle::begin($value)->notNull();
```

### AssertNumeric

Defines assertion methods for numeric values.

### `exact(int|float $expected): self`

Asserts that the value is exactly equal to `$expected` (===).

**Example:**

```php
AssertNumeric::begin($value)->exact(42);
```

### `notExact(int|float $unexpected): self`

Asserts that the value is not exactly equal to `$unexpected` (===).

**Example:**

```php
AssertNumeric::begin($value)->notExact(36);
```

### `isGreaterThan(int|float $min): self`

Asserts that the value is greater than `$min`.

**Example:**

```php
AssertNumeric::begin($number)->isGreaterThan(10);
```

### `isLessThan(int|float $max): self`

Asserts that the value is less than `$max`.

**Example:**

```php
AssertNumeric::begin($number)->isLessThan(100);
```

### `isBetween(int|float $min, int|float $max, bool $inclusive = true)`

Asserts that the value is between `$min` and `$max`.

**Example:**

```php
AssertNumeric::begin($number)->isBetween(1, 10);
```

### AssertArray

Defines assertion methods for arrays.

### `equals(array $expected): self`

Asserts that the array is equal to `$expected` (==).

**Example:**

```php
AssertArray::begin($array)->equals(['a', 'b']);
```

### `notEquals(array $unexpected): self`

Asserts that the array is not equal to `$unexpected` (==).

**Example:**

```php
AssertArray::begin($array)->notEquals([]);
```

### `exact(array $expected): self`

Asserts that the array is exactly equal to `$expected` (===).

**Example:**

```php
AssertArray::begin($array)->exact(['a' => 1, 'b' => 2]);
```

### `notExact(array $unexpected): self`

Asserts that the array is not exactly equal to `$unexpected` (===).

**Example:**

```php
AssertArray::begin($array)->notExact(['a' => 2]);
```

### `empty(): self`

Asserts that the array is empty.

**Example:**

```php
AssertArray::begin($array)->empty();
```

### `notEmpty(): self`

Asserts that the array is not empty.

**Example:**

```php
AssertArray::begin($array)->notEmpty();
```

### `contains(mixed $element, bool $shouldUseStrict = true): self`

Asserts that the array contains the given element. If `$shouldUseStrict` is `true` the type is checked as well.

**Example:**

```php
AssertArray::begin($array)->contains('foo');
```

### `countEquals(int $count): self`

Asserts that the array has exactly `$count` elements.

**Example:**

```php
AssertArray::begin($array)->countEquals(3);
```

### `hasKey(mixed $key): self`

Asserts that the array has the specified key.

**Example:**

```php
AssertArray::begin($array)->hasKey('id');
```

### `notHasKey(mixed $key): self`

Asserts that the array does not have the specified key.

**Example:**

```php
AssertArray::begin($array)->notHasKey('password');
```

### `keysEqual(array $expectedKeys): self`

Asserts that the array's keys are equal to `$expectedKeys`.

**Example:**

```php
AssertArray::begin($array)->keysEqual(['id', 'name']);
```

### `containsOnly(array $allowedValues): self`

Asserts that the array contains only the specified values.

**Example:**

```php
AssertArray::begin($array)->containsOnly([1, 2, 3]);
```

## AssertMock

Defines assertion methods for mock objects.

> **Note:** Static calls are not available for `AssertMock` methods. Use `AssertMock::begin($mock)` to initiate the assertion via fluent api.

---

### `isCalledTimes(string $method, int $count): self`

Asserts that the specified method was called **exactly** `$count` times.

**Example:**

```php
AssertMock::begin($mock)->isCalledTimes('foo', 2);
```

---

### `isCalledOnce(string $method): self`

Asserts that the specified method was called **exactly once**.

**Example:**

```php
AssertMock::begin($mock)->isCalledOnce('foo');
```

---

### `isNotCalled(string $method): self`

Asserts that the specified method was **never called**.

**Example:**

```php
AssertMock::begin($mock)->isNotCalled('foo');
```

---

### `isCalledAtLeast(string $method, int $minCallCount): self`

Asserts that the specified method was called **at least** `$minCallCount` times.

**Example:**

```php
AssertMock::begin($mock)->isCalledAtLeast('foo', 3);
```

---

### `isCalledMoreThan(string $method, int $minCallCount): self`

Asserts that the specified method was called **more than** `$minCallCount` times.

**Example:**

```php
AssertMock::begin($mock)->isCalledMoreThan('foo', 2);
```

---

### `isCalledAtMost(string $method, int $maxCallCount): self`

Asserts that the specified method was called **at most** `$maxCallCount` times.

**Example:**

```php
AssertMock::begin($mock)->isCalledAtMost('foo', 5);
```

---

### `isCalledLessThan(string $method, int $maxCallCount): self`

Asserts that the specified method was called **less than** `$maxCallCount` times.

**Example:**

```php
AssertMock::begin($mock)->isCalledLessThan('foo', 3);
```

---

### `isCalledWith(string $method, array $args, bool $showActualMethodCallsOnError = true): self`

Asserts that the method was called **at least once** with the given arguments.

If `$showActualMethodCallsOnError` is set to `false` the actual method calls along with their arguments are not going to be printed in the erorr message. This saves a bit of time when executing the test.

**Example:**

```php
AssertMock::begin($mock)->isCalledWith('bar', [1, 2]);
```

### `isCalledWithOnSpecificCall(string $method, array $args, int $onCall): self`

Asserts that the method was called with the given arguments **on a specific call number** (1-based index).

**Example:**

```php
AssertMock::begin($mock)->isCalledWithOnSpecificCall('foo', ['arg'], 2);
```

### `isOnlyCalledWith(string $method, array $expectedArgs, bool $showActualMethodCallsOnError = true): self`

Asserts that the method was **only** called with the given arguments.

If `$showActualMethodCallsOnError` is set to `false` the actual method calls along with their arguments are not going to be printed in the erorr message. This saves a bit of time when executing the test.

**Example:**

```php
AssertMock::begin($mock)->isOnlyCalledWith('bar', [1, 2]);
```

### `isOnlyCalledWithMatchingArgs(string $method, callable $matcher, bool $showActualMethodCallsOnError = true): self`

Asserts that the method was **only** called with arguments where `$matcher` returns `true`.

If `$showActualMethodCallsOnError` is set to `false` the actual method calls along with their arguments are not going to be printed in the erorr message. This saves a bit of time when executing the test.

**Example:**

```php
AssertMock::begin($mock)->isOnlyCalledWithMatchingArgs('bar', function (array $args): bool {
                    $arg1 = $args[0];
                    $arg2 = $args[1];

                    if ($arg1 !== 'foo') {
                        return false;
                    }

                    if ($arg2 !== 15 || $arg2 !== 7) {
                        return false;
                    }

                    return true;
                });
```

### `isCalledWithMatchingOnSpecificCall(string $method, callable $matcher, int $onCall): self`

Asserts that the method was called with arguments where `$matcher` returns `true` **on a specifc call**.

**Example:**

```php
AssertMock::begin($mock)->isOnlyCalledWithMatchingArgs('bar', function (array $args): bool {
                    $arg1 = $args[0];
                    $arg2 = $args[1];

                    if ($arg1 !== 'foo') {
                        return false;
                    }

                    return true;
                }, 2) // -> Only relevant when method is called the second time !
```

### `isCalledOn(string $method, int $callNumber): self`

Checks that the method was called at a **specific point** across all method calls made on the mock.

```php
AssertMock::begin($mock)->isCalledOn('bar', 2) // 'bar' has to be the second method that get's called on the mock
```

### `checkMethod(string $method, callable $assertMethod): self`

Provides a fluent way to group method assertions using a callback that receives an `AssertMockMethod` instance. See [AssertMockMethod](#assertmockmethod) for details.

**Example:**

```php
AssertMock::begin($mock)->checkMethod('foo', function ($assert) {
    $assert->isCalledOnce()->isCalledWith(['x']);
});
```

---

## AssertMockMethod

Helper class for scoped assertions on a **specific mock method**, used via `$assertMock->checkMethod()`. See [checkMethod description](#checkmethodstring-method-callable-assertmethod-self) for details.

---

### `isCalledTimes(int $expectedCallCount): self`

Asserts that the method was called exactly `$expectedCallCount` times.

---

### `isCalledOnce(): self`

Asserts that the method was called exactly once.

---

### `isNotCalled(): self`

Asserts that the method was never called.

---

### `isCalledAtLeast(int $minCallCount): self`

Asserts that the method was called at least `$minCallCount` times.

---

### `isCalledMoreThan(int $minCallCount): self`

Asserts that the method was called more than `$minCallCount` times.

---

### `isCalledAtMost(int $maxCallCount): self`

Asserts that the method was called at most `$maxCallCount` times.

---

### `isCalledLessThan(int $maxCallCount): self`

Asserts that the method was called less than `$maxCallCount` times.

---

### `isCalledWith(array $expectedArgs, bool $showActualMethodCallsOnError = true): self`

Asserts that the method was called at least once with the given arguments.

if `$showActualMethodCallsOnError` is set to `false` the actual method calls and their arguments are not going to be printed in the error message. This saves a bit of time when executing the test.

---

### `isCalledWithOnSpecificCall(array $expectedArgs, int $onCall): self`

Asserts that the method was called with the given arguments on a specific call number.

### `isOnlyCalledWith(array $expectedArgs, bool $showActualMethodCallsOnError = true): self`

Asserts that the method was **only** called with the given arguments.

If `$showActualMethodCallsOnError` is set to `false` the actual method calls along with their arguments are not going to be printed in the erorr message. This saves a bit of time when executing the test.

### `isOnlyCalledWithMatchingArgs(callable $matcher, bool $showActualMethodCallsOnError = true): self`

Asserts that the method was **only** called with arguments where `$matcher` returns `true`.

If `$showActualMethodCallsOnError` is set to `false` the actual method calls along with their arguments are not going to be printed in the erorr message. This saves a bit of time when executing the test.

### `isCalledWithMatchingOnSpecificCall(callable $matcher, int $onCall): self`

Asserts that the method was called with arguments where `$matcher` returns `true` **on a specifc call**.

### `isCalledOn(string $method, int $callNumber): self`

Checks that the method was called at a **specific point** across all method calls made on the mock.

---

**[⬆ Back to Top](#)** • **[📘 Home](index.md)**
