---
layout: default
---

**[← Back to Home](index.md)**

# Mocking

MicroUnit provides a flexible mocking engine.  
Each method is listed below with its signature and a concise explanation.

For an explanation on how to perform assertions on mocks see [Assertions](assertions.md).

---

## Table of Contents

- **[MockBuilder](#mockbuilder)**
- **[MicroMock](#micromock)**
- **[CallLog](#calllog)**
- **[Full Example](#full-example)**

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

Sets a callback that is executed when the method gets called.

```php
$mockBuilder->returnsCallback('sum', fn($a, $b) => $a + $b);
```

### `throws(string $method, \Throwable $e): self`

Makes the method throw the given throwable.

```php
$mockBuilder->throws('fail', new Exception('fail!'));
```

### `keepOriginalMethodBehaviour(string $method): self` ![Not Yet Released](https://img.shields.io/badge/status-not%20yet%20released-red)

_This method will be available in the next release._

Enables execution of the original method logic contained inside the class to be mocked.

if no custom return is defined the method will return its original return value. If a custom return is defined the method will return the custom value.

```php
$mock = $mockBuilder->keepOriginalMethodBehaviour('foo');
```

### `disableOriginalConstructor(): self` ![Not Yet Released](https://img.shields.io/badge/status-not%20yet%20released-red)

_This method will be available in the next release._

Defines that when creating a mock instance the original constructor of the mocked class should not be called.

```php
$mock = $mockBuilder->disableOrginalConstructor();
```

### `executeInConstructor(callable $fn): self` ![Not Yet Released](https://img.shields.io/badge/status-not%20yet%20released-red)

_This method will be available in the next release._

Sets a callable that will be executed inside the constructor.
If the original constructor is getting called the callable will be executed after the original constructor was called.  
Otherwise it will be executed right away.

```php
$mock = $mockBuilder->executeInConstructor(function (array $constructorArgs) {
            echo $constructorArgs[0];
        });
```

### `withConstructorArgs(array $args): self` ![Not Yet Released](https://img.shields.io/badge/status-not%20yet%20released-red)

_This method will be available in the next release._

Sets the arguments that are going to be passed to the constructor and original constructor when an instance of the mocked class is created.

```php
$mock = $mockBuilder->withConstructorArgs([12, 5]);
```

### `build(): MicroMock`

Finalizes and returns the mock object (`MicroUnit\Mocking\MicroMock`).

```php
$mock = $mockBuilder->build();
```

See [MicroMock section](#micromock) for information on how that obtained mock object can be used.

---

## MicroMock

> **Note:** The `MicroMock` class contains a couple of public properties that are used by the `MockBuilder` internally. Altering them on `MicroMock` directly is **not** recommended and may lead to unexpected behaviour.

### `newInstance(): object`

Creates a new instance of the mocked class.

This instance can be used where an instance of the mocked class is expected.

```php
$instance = $mock->newInstance();
```

### `newAlternateInstance(array $constructorArgs): object` ![Not Yet Released](https://img.shields.io/badge/status-not%20yet%20released-red)

_This method will be available in the next release._

Creates an alternate instance of the mocked class using the specified constructor arguments instead of the ones defined during mock building (via `$mockBuilder->withConstructorArgs(array $args)`).

This instance can be used where an instance of the mocked class is expected.

```php
$instance = $mock->newAlternateInstance(['foo', 17]);
```

### `getCallLog(): CallLog`

Returns the call log for the mock. See [CallLog Section](#calllog) for details.

```php
$log = $mock->getCallLog();
```

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

# Full Example

```php
use MicroUnit\Mocking\MockBuilder;
use MicroUnit\Assertion\AssertMock;

use Exception;

//In this example we mock a class but you can also mock abstract classes and interfaces of course.
class UserService {

    public function __construct(protected string $prefix = '') {}

    public function getUserName(int $id): string {
            return "Default";
    }

    public function getNextId(): int {
        return 0;
    }

    public function addUser(string $name): bool {
        return true;
    }

}

// Build a mock for UserService
$mockBuilder = MockBuilder::create(UserService::class)
    ->returns('getUserName', 'MockedUser')               // fixed return
    ->returnsSequence('getNextId', 101, 102, 103)        // sequence
    ->returnsCallback('addUser', fn($name) => $name !== '') // dynamic callback
->withConstructorArgs(['USR_']); // mock constructor arg

$mock = $mockBuilder->build();

// Create mock instance (will use constructor args ['USR_'])
$instance = $mock->newInstance();

echo $instance->getUserName(1);       // Output: MockedUser
echo $instance->getNextId();          // Output: 101
echo $instance->getNextId();          // Output: 102
var_dump($instance->addUser('John')); // Output: true
var_dump($instance->addUser('')); // Output: false

// Create alternate mock instance with different constructor args
$altInstance = $mock->newAlternateInstance(['ALT_']);
$altInstance->getUserName(42);

// Assertions
AssertMock::begin($mock)
    ->isCalledTimes('getUserName', 2)
    ->isCalledAtLeast('getNextId', 2)
    ->checkMethod('addUser', function ($assert) { // Perform multiple assertions on the same method easily
        $assert
        ->isCalledWith(['Alice']) // check method was called at least once with the argument `Alice`
        ->isCalledWithOnSpecificCall([''], 2); // check second call had empty string
    });

```

---

**[⬆ Back to Top](#)** • **[📘 Home](index.md)**
