---
layout: default
---

**[← Back to Home](/)**

# Mocking

MicroUnit provides a flexible mocking engine.  
Each method is listed below with its signature and a concise explanation.

---

## MockBuilder

### `create($className)`

Creates a mock builder for the given class.

```php
$mock = MockBuilder::create(SomeClass::class);
```

### `returns($method, $value)`

Sets a return value for a method.

```php
$mockBuilder->returns('getName', 'John');
```

### `returnsSequence($method, ...$values)`

Sets a sequence of return values for a method. It moves from one return value to the next each time the method is called.

```php
$mockBuilder->returnsSequence('next', 'first', 'second', 'third');

//Call 1: returns 'first'
//Call 2: returns 'second'
//Call 3: returns 'third'
//Call 4 and after: returns null

```

### `returnsCallback($method, callable $fn)`

Sets a callback that is executed when the method get's called.

```php
$mockBuilder->returnsCallback('sum', fn($a, $b) => $a + $b);
```

### `throws($method, $exception)`

Makes the method throw the given exception.

```php
$mockBuilder->throws('fail', new Exception('fail!'));
```

### `build()`

Finalizes and returns the mock object (`MicroUnit\Mocking\MicroMock`).

See upcoming section for information on how that obtained instance can be used.

```php
$mock = $mockBuilder->build();
```

---

## MicroMock

### `newInstance()`

Creates a new instance of the mocked class.

This instance can be used where an instance of the mocked class is expected.

```php
$instance = $mock->newInstance();
```

### `getCallLog()`

Returns the call log for the mock.

```php
$log = $mock->getCallLog();
```

This will give you an array similar to the following sample.

```php
[
    'fooMethod' => [
        'callLog' => 2, //Number of times the method was called
        'argLog' => [
            [1, 2],
            ['bar' => 'baz'],
        ], // Arguments passed for each call (argLog[0] = arguments for first call and so on...)
    ],
    'barMethod' => [
        'callLog' => 1,
        'argLog' => [
            [],
        ],
    ],
]
```

---

## CallLog

### `getCallCount($method)`

Returns the number of times a method was called.

```php
$count = $log->getCallCount('foo');
```

### `getAllCallArgs($method)`

Returns all arguments passed to a method.

```php
$args = $log->getAllCallArgs('foo');
```

---

**[⬆ Back to Top](#table-of-contents)** • **[📘 Home](/)**
