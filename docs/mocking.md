---
layout: default
---

**[← Back to Home](index.md)**

# Mocking

MicroUnit provides a flexible mocking engine.  
Each method is listed below with its signature and a concise explanation.

For an explanation on how to perform assertions on mocks see [Assertions](assertion.md).

---

## Table of Contents

- **[MockBuilder](#mockbuilder)**
- **[MicroMock](#micromock)**
- **[CallLog](#calllog)**

---

## MockBuilder

### `create(string $class): self`

Creates a mock builder for the given class.

```php
$mock = MockBuilder::create(SomeClass::class);
```

### `returns(string $method, mixed $value): self`

Sets a return value for a method.

```php
$mockBuilder->returns('getName', 'John');
```

### `returnsSequence(string $method, mixed ...$values): self`

Sets a sequence of return values for a method. It moves from one return value to the next each time the method is called.

```php
$mockBuilder->returnsSequence('next', 'first', 'second', 'third');

//Call 1: returns 'first'
//Call 2: returns 'second'
//Call 3: returns 'third'
//Call 4 and after: returns null

```

### `returnsCallback(string $method, callable $fn): self`

Sets a callback that is executed when the method get's called.

```php
$mockBuilder->returnsCallback('sum', fn($a, $b) => $a + $b);
```

### `throws(string $method, \Throwable $e): self`

Makes the method throw the given throwable.

```php
$mockBuilder->throws('fail', new Exception('fail!'));
```

### `build(): MicroMock`

Finalizes and returns the mock object (`MicroUnit\Mocking\MicroMock`).

```php
$mock = $mockBuilder->build();
```

See [MicroMock section ](#micromock) for information on how that obtained mock object can be used.

---

## MicroMock

### `newInstance(): object`

Creates a new instance of the mocked class.

This instance can be used where an instance of the mocked class is expected.

```php
$instance = $mock->newInstance();
```

### `getCallLog(): CallLog`

Returns the call log for the mock. See [CallLog Section](#calllog) for details.

```php
$log = $mock->getCallLog();
```

### `setReturnPlan(string $method, ReturnPlanType $returnType, mixed $return): void`

Used by the `MockBuilder` to define the different returns for methods. Calling this method directly on `MicroMock` is not recommended.

### `handleCall(string $method, array $args): mixed`

> **⚠️ Warning:** This method is intended for internal use only. Do **not** call it directly.

Invoked internally by the auto-generated methods in mock classes to execute defined behavior and track method calls.

## CallLog

A utility class that wraps internal method call data, providing helper methods to access and inspect call details.

> **Note:** For most use cases, it is recommended to validate method calls using the [AssertMock class](assertions.md#assertmock), which uses `Calllog` internally to provide assertion methods for mocks.

### `getCallCount(string $method): int`

Returns the number of times a method was called.

```php
$count = $log->getCallCount('foo');
```

### `getAllCallArgs(string $method): array`

Returns all arguments passed to a method.

```php
$args = $log->getAllCallArgs('foo');
```

### `hasCalls(string $method): bool`

Checks if the given method has any calls.

```php
$args = $log->hasCalls('foo');
```

---

**[⬆ Back to Top](#)** • **[📘 Home](index.md)**
