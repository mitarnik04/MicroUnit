---
layout: default
---

**[← Back to Home](/)**

# Assertions

MicroUnit provides several assertion classes.  
Each assertion method is listed below with its signature, a concise explanation, and usage examples.  
Most assertion methods can be called either via an assertion chain (e.g., `AssertSingle::begin($value)->equals(...)`) or statically (e.g., `Assert::equals($value, $expected)`).  
**Note:** Static calls are not available for `AssertMock` methods due to additional setup required for mock tracking.

---

## Static Assert Methods

Most assertion methods can be called statically via the `Assert` class.
These static methods get used under the hood by AssertSingle, AssertArray and AssertNumeric (Fluent API).

### List of Available Static Methods

- `Assert::equals($value, $expected)`  
  Asserts that `$value == $expected`.

- `Assert::notEquals($value, $unexpected)`  
  Asserts that `$value != $unexpected`.

- `Assert::exact($value, $expected)`  
  Asserts that `$value === $expected`.

- `Assert::notExact($value, $unexpected)`  
  Asserts that `$value !== $unexpected`.

- `Assert::isTrue($value)`  
  Asserts that `$value === true`.

- `Assert::isFalse($value)`  
  Asserts that `$value === false`.

- `Assert::isNull($value)`  
  Asserts that `$value === null`.

- `Assert::notNull($value)`  
  Asserts that `$value !== null`.

- `Assert::isGreaterThan($value, $min)`  
  Asserts that `$value > $min`.

- `Assert::isLessThan($value, $max)`  
  Asserts that `$value < $max`.

- `Assert::isBetween($value, $min, $max, $inclusive = true)`  
  Asserts that `$value` is between `$min` and `$max`. Inclusive by default.

- `Assert::empty($array)`  
  Asserts that the array is empty.

- `Assert::notEmpty($array)`  
  Asserts that the array is not empty.

- `Assert::contains($array, $element)`  
  Asserts that `$element` exists in `$array`.

- `Assert::countEquals($array, $count)`  
  Asserts that the array contains exactly `$count` elements.

- `Assert::hasKey($array, $key)`  
  Asserts that the array has the specified key.

- `Assert::notHasKey($array, $key)`  
  Asserts that the array does not have the specified key.

- `Assert::keysEqual($array, $expectedKeys)`  
  Asserts that the keys of `$array` match `$expectedKeys` in order and value.

- `Assert::containsOnly($array, $allowedValues)`  
  Asserts that all elements in `$array` are among `$allowedValues`.

- `Assert::instanceOf($value, $className)`  
   Asserts that `$value` is an instance of the given class or interface.

- `Assert::throws(callable $fn, $exceptionClass)`  
  Asserts that executing `$fn` throws an exception of type `$exceptionClass`.

> **Note:**  
> Static assert methods are **not available** for `AssertMock` because mock assertions require additional context and tracking.

## Fluent Assertions

### AssertSingle

Defines different assertion methods for single values (bool, string, int, float, etc.).

### `equals($expected)`

Asserts that the value is equal to `$expected` (==).

**Example:**

```php
AssertSingle::begin($value)->equals(42);
```

### `notEquals($unexpected)`

Asserts that the value is not equal to `$unexpected` (==).

**Example:**

```php
AssertSingle::begin($value)->notEquals(0);
```

### `exact($expected)`

Asserts that the value is exactly equal to `$expected` (===).

**Example:**

```php
AssertSingle::begin($value)->exact('foo');
```

### `notExact($unexpected)`

Asserts that the value is not exactly equal to `$unexpected` (===).

**Example:**

```php
AssertSingle::begin($value)->notExact(false);
```

### `instanceOf($className)`

Asserts that the value is an instance of the given class/interface.

**Example:**

```php
AssertSingle::begin($object)->instanceOf(DateTime::class);
```

### `isTrue()`

Asserts that the value is `true`.

**Example:**

```php
AssertSingle::begin($flag)->isTrue();
```

### `isFalse()`

Asserts that the value is `false`.

**Example:**

```php
AssertSingle::begin($flag)->isFalse();
```

### `isNull()`

Asserts that the value is `null`.

**Example:**

```php
AssertSingle::begin($value)->isNull();
```

### `notNull()`

Asserts that the value is not `null`.

**Example:**

```php
AssertSingle::begin($value)->notNull();
```

### AssertNumeric

Defines assertion methods for numeric values.

### `isGreaterThan($min)`

Asserts that the value is greater than `$min`.

**Example:**

```php
AssertNumeric::begin($number)->isGreaterThan(10);
```

### `isLessThan($max)`

Asserts that the value is less than `$max`.

**Example:**

```php
AssertNumeric::begin($number)->isLessThan(100);
```

### `isBetween($min, $max, $inclusive = true)`

Asserts that the value is between `$min` and `$max` (inclusive by default).

**Example:**

```php
AssertNumeric::begin($number)->isBetween(1, 10);
```

### AssertArray

Defines assertion methods for arrays.

### `equals($expected)`

Asserts that the array is equal to `$expected` (==).

**Example:**

```php
AssertArray::begin($array)->equals(['a', 'b']);
```

### `notEquals($unexpected)`

Asserts that the array is not equal to `$unexpected` (==).

**Example:**

```php
AssertArray::begin($array)->notEquals([]);
```

### `exact($expected)`

Asserts that the array is exactly equal to `$expected` (===).

**Example:**

```php
AssertArray::begin($array)->exact(['a' => 1, 'b' => 2]);
```

### `notExact($unexpected)`

Asserts that the array is not exactly equal to `$unexpected` (===).

**Example:**

```php
AssertArray::begin($array)->notExact(['a' => 2]);
```

### `empty()`

Asserts that the array is empty.

**Example:**

```php
AssertArray::begin($array)->empty();
```

### `notEmpty()`

Asserts that the array is not empty.

**Example:**

```php
AssertArray::begin($array)->notEmpty();
```

### `contains($element)`

Asserts that the array contains the given element.

**Example:**

```php
AssertArray::begin($array)->contains('foo');
```

### `countEquals($count)`

Asserts that the array has exactly `$count` elements.

**Example:**

```php
AssertArray::begin($array)->countEquals(3);
```

### `hasKey($key)`

Asserts that the array has the specified key.

**Example:**

```php
AssertArray::begin($array)->hasKey('id');
```

### `notHasKey($key)`

Asserts that the array does not have the specified key.

**Example:**

```php
AssertArray::begin($array)->notHasKey('password');
```

### `keysEqual($expectedKeys)`

Asserts that the array's keys are equal to `$expectedKeys`.

**Example:**

```php
AssertArray::begin($array)->keysEqual(['id', 'name']);
```

### `containsOnly($allowedValues)`

Asserts that the array contains only the specified values.

**Example:**

```php
AssertArray::begin($array)->containsOnly([1, 2, 3]);
```

### AssertMock

Defines assertion methods for mock objects.  
**Note:** Static calls are not available for `AssertMock` methods.

### `isCalledTimes($method, $count)`

Asserts that the mock method was called `$count` times.

**Example:**

```php
AssertMock::begin($mock)->isCalledTimes('foo', 2);
```

### `isCalledWith($method, $args)`

Asserts that the mock method was called with the specified arguments.

**Example:**

```php
AssertMock::begin($mock)->isCalledWith('bar', [1, 2]);
```

---

**[⬆ Back to Top](#table-of-contents)** • **[📘 Home](/)**
