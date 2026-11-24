---
layout: default
title: Mocking
description: Build mocks of classes and interfaces, decide what they return, and assert on the calls they received.
---

# Mocking

MicroUnit ships its own mocking engine, so there is no Prophecy or Mockery to install. You describe a mock with `MockBuilder`, get a `MicroMock` from `build()`, create instances from it, and check the calls afterwards with `AssertMock`. This page covers all three parts, plus mocks as fixtures, what cannot be mocked, and how PHPUnit's mock API maps to MicroUnit's.

Configuration and assertions are separate on purpose: the test first says what the collaborators return, then runs the code, then states what should have happened. It reads top to bottom, with no expectations hidden in the setup that only fire when the test ends.

## A first mock

```php
use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertMock;
use MicroUnit\Mock\MockBuilder;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('Signup');

$tester->define('sends_a_welcome_mail', function () {
    $mailer = MockBuilder::create(Mailer::class)
        ->returns('send', true)
        ->build()
        ->newInstance();

    (new Signup($mailer))->register('ada@example.com');

    AssertMock::begin($mailer)
        ->isCalledOnce('send')
        ->isCalledWith('send', ['ada@example.com', 'Welcome']);
});
```

Three steps, always in this order:

1. `MockBuilder::create(Mailer::class)` starts a definition, `returns()` and friends configure it, `build()` turns it into a `MicroMock`.
2. `newInstance()` creates an object that extends the class (or implements the interface), so it fits every type hint.
3. `AssertMock::begin($mailer)` checks the calls after the code ran.

You can mock classes, abstract classes and interfaces, including readonly classes and PHP's own classes such as `DateTime`.

## What a mock does by default

Every public method of the mock is intercepted: the call is recorded and a value is returned without running the real code. Abstract protected methods are intercepted too; concrete protected methods keep their real behaviour unless you configure them on the builder or name them in `interceptOnly()`.

When you have not configured a method, it returns a neutral value for its return type:

| Return type | Default |
| --- | --- |
| none, `mixed`, `void` or nullable (`?Foo`, `Foo\|null`) | `null` |
| `int` / `float` / `string` / `bool` | `0` / `0.0` / `''` / `false` |
| `array`, `iterable` | `[]` |
| `callable` | a closure that returns `null` |
| `static`, `self`, `object`, or any type the mock itself satisfies | the mock instance, so fluent interfaces keep working |
| any other class or interface | throws a `MockException` asking you to configure it |

That last row is deliberate. Inventing an object of some other type would let a test pass for the wrong reason, so MicroUnit tells you instead:

```text
Clock::now() must return DateTimeImmutable, but no return value is configured. Use returns(), returnsSequence() or returnsCallback().
```

## MockBuilder

### `create`

```php
MockBuilder::create(string $targetType): MockBuilder
```

Starts a mock of a class, abstract class or interface.

### `returns`

```php
returns(string $method, mixed $value): MockBuilder
```

The method always returns `$value`.

```php
->returns('find', new User('Ada'))
```

### `returnsSequence`

```php
returnsSequence(string $method, mixed ...$values): MockBuilder
```

Each call returns the next value. Once the values run out, the method returns its default from the table above.

```php
->returnsSequence('nextId', 101, 102, 103)
```

### `returnsCallback`

```php
returnsCallback(string $method, callable $callback): MockBuilder
```

The callback receives the call's arguments and its return value is returned. For results that depend on the input.

```php
->returnsCallback('price', fn (string $currency, int $quantity = 1) => $quantity * 2.5)
```

### `returnsMap`

```php
returnsMap(string $method, array $map): MockBuilder
```

A lookup table. Each row lists arguments followed by the return value. The first row whose arguments strictly equal the call's leading arguments wins. Without a matching row, the method returns its default.

```php
->returnsMap('price', [
    ['USD', 2, 22.0], // price('USD', 2)
    ['USD', 11.0],    // price('USD', anything else)
    ['EUR', 10.0],
])
```

Rows match on a prefix of the arguments, so put the more specific rows first.

### `throws`

```php
throws(string $method, \Throwable $throwable): MockBuilder
```

The method throws this exception on every call. For testing the unhappy path.

```php
->throws('send', new TransportException('SMTP server not reachable'))
```

Configuring the same method twice replaces the earlier configuration.

### `keepOriginalMethodBehaviour`

```php
keepOriginalMethodBehaviour(string $method): MockBuilder
```

Runs the real implementation of this method while still recording the call. If you also configure a return value, the real method runs first (for its side effects) and the configured value is returned.

```php
$mock = MockBuilder::create(PriceCalculator::class)
    ->keepOriginalMethodBehaviour('net')
    ->returns('taxRate', 0.2)
    ->build();
```

Abstract methods have no implementation to keep; asking for one throws a `MockException` at `build()`.

### `interceptOnly`: partial mocks

```php
interceptOnly(string ...$methods): MockBuilder
```

Flips the default: only these methods are intercepted, every other method runs its real code. Calls to the other public methods are still recorded. This is what you want when a class is mostly fine to use for real and you only need to cut off one dependency, like the method that calls an API.

```php
$mock = MockBuilder::create(Greeter::class)
    ->interceptOnly('greet')
    ->returns('greet', 'Hi, Ada!')
    ->build();
$greeter = $mock->newInstance();

Assert::exact('HI, ADA!', $greeter->shout('Ada')); // real shout(), mocked greet()
AssertMock::begin($greeter)->isCalledOnce('shout')->isCalledOnce('greet');
```

### Constructors: `disableOriginalConstructor`, `withConstructorArgs`, `executeInConstructor`

```php
disableOriginalConstructor(): MockBuilder
withConstructorArgs(array $args): MockBuilder
executeInConstructor(callable $callback): MockBuilder
```

By default `newInstance()` calls the real constructor, without arguments. `withConstructorArgs()` sets the arguments, `disableOriginalConstructor()` skips the constructor entirely (handy when it opens connections or needs things you do not have in a test).

`executeInConstructor()` runs a callback for every new instance, after the original constructor if that runs. It receives the instance and the constructor arguments.

```php
$mock = MockBuilder::create(Greeter::class)
    ->withConstructorArgs(['Howdy'])
    ->keepOriginalMethodBehaviour('greet')
    ->executeInConstructor(function (Greeter $greeter, array $args) {
        $greeter->log[] = 'created with ' . implode(', ', $args);
    })
    ->build();

Assert::exact('Howdy, Ada!', $mock->newInstance()->greet('Ada'));
```

Instances are registered before the constructor runs, so calls the constructor makes to mocked methods are recorded and answered like any other call.

### `build`

```php
build(): MicroMock
```

Generates the mock class and returns the `MicroMock`. This is where problems are reported: a final class, a method that does not exist, a static or private method you tried to configure. The class is generated once per shape and reused, so building the same mock in a hundred tests costs one class generation.

## MicroMock

`build()` returns a `MicroMock`. It creates instances, keeps the call logs and lets you change return values later.

### Instances: `newInstance`, `newAlternateInstance`

```php
newInstance(): object
newAlternateInstance(array $constructorArgs): object
```

`newInstance()` uses the constructor arguments from the builder, `newAlternateInstance()` the ones you pass. One mock can create any number of instances; all of them share its configuration.

```php
$mock = MockBuilder::create(Greeter::class)->withConstructorArgs(['Hello'])->build();
$english = $mock->newInstance();
$austrian = $mock->newAlternateInstance(['Servus']);
```

Mock instances cannot be cloned: a clone is not registered with its mock, and calling a method on it throws a `MockException`.

### `MicroMock::of`

```php
MicroMock::of(object $instance): MicroMock
```

Returns the mock that created an instance. Usually you only hold the instance (it is what the code under test needs), and this gets you back to the mock when you want to reconfigure it. Passing an object that is not a mock instance throws a `MockException`.

### Changing return values after `build()`

```php
returns(string $method, mixed $value): MicroMock
returnsSequence(string $method, mixed ...$values): MicroMock
returnsCallback(string $method, callable $callback): MicroMock
returnsMap(string $method, array $map): MicroMock
throws(string $method, \Throwable $throwable): MicroMock
```

The same methods as on the builder. The change applies to every instance of the mock, existing ones included, and a new sequence starts from its first value. This is what makes mocks work as [fixtures](#mocks-as-fixtures): the fixture builds the mock, each test decides what it returns.

```php
MicroMock::of($mailer)->throws('send', new TransportException('timeout'));
```

Only methods the mock intercepts can be reconfigured. A method that runs its real code (outside `interceptOnly()`, or kept with `keepOriginalMethodBehaviour()` without a return value at build time) throws a `MockException`, because the generated class has no hook for it.

### Call logs

```php
getCallLog(): CallLog
getInstanceCallLog(object $instance): CallLog
getTargetType(): string
```

Every mock keeps two kinds of call logs: one across all instances it created, and one per instance. `AssertMock` reads them for you, so you rarely need them directly. When you do, a `CallLog` offers:

- `getCallCount($method)`
- `getAllCallArgs($method)`
- `getCallArgs($method, $callNumber)` (1-based, `null` if there was no such call)
- `hasCalls($method)`
- `getCallSequence()` (method names in call order)

Arguments are recorded as passed. Default values of parameters you left out are not filled in, so a call to `price('EUR')` on `price(string $currency, int $quantity = 1)` is recorded as `['EUR']`. Named arguments are recorded in parameter order.

## AssertMock

`AssertMock::begin()` takes either the `MicroMock` or one of its instances, and that choice decides which calls you are looking at:

```php
AssertMock::begin($mock);     // calls on every instance the mock created
AssertMock::begin($instance); // calls on this one instance only
```

Every method returns the `AssertMock`, so checks chain. A failure names the mocked type, the method and what actually happened:

```text
Expected Mailer instance: send() to be called exactly 1 time(s), but it was called 0 time(s).
```

### Call counts

| Method | Passes when the method was called |
| --- | --- |
| `isCalled(string $method)` | at least once |
| `isCalledOnce(string $method)` | exactly once |
| `isCalledTimes(string $method, int $expectedCallCount)` | exactly n times |
| `isNotCalled(string $method)` | never |
| `isCalledAtLeast(string $method, int $minCallCount)` | n times or more |
| `isCalledAtMost(string $method, int $maxCallCount)` | n times or fewer |
| `isCalledMoreThan(string $method, int $callCount)` | more than n times |
| `isCalledLessThan(string $method, int $callCount)` | fewer than n times |

```php
AssertMock::begin($cache)
    ->isCalledOnce('get')
    ->isNotCalled('set');
```

### Arguments

| Method | Passes when |
| --- | --- |
| `isCalledWith(string $method, array $expectedArgs)` | at least one call received exactly these arguments |
| `isOnlyCalledWith(string $method, array $expectedArgs)` | every call received exactly these arguments |
| `isCalledWithOnSpecificCall(string $method, array $expectedArgs, int $callNumber)` | call number n (1-based) received these arguments |
| `isOnlyCalledWithMatchingArgs(string $method, callable $matcher)` | `$matcher($args)` returns `true` for every call |
| `isCalledWithMatchingOnSpecificCall(string $method, callable $matcher, int $callNumber)` | `$matcher($args)` returns `true` for call number n |

Arguments are compared strictly (`===`), as a list in parameter order. For objects that means the same instance. When the code under test creates the object itself, compare with a matcher instead:

```php
$mailer->send('ada@example.com', 'Welcome');
$mailer->send('grace@example.com', 'Your invoice');

AssertMock::begin($mailer)
    ->isCalledWith('send', ['ada@example.com', 'Welcome'])
    ->isCalledWithOnSpecificCall('send', ['grace@example.com', 'Your invoice'], 2)
    ->isOnlyCalledWithMatchingArgs('send', fn (array $args) => str_ends_with($args[0], '@example.com'));

AssertMock::begin($wallet)
    ->isCalledWithMatchingOnSpecificCall('add', fn (?array $args) => $args[0]->amount === 5, 1);
```

A matcher for a specific call receives `null` when that call did not happen.

### Order: `isCalledOn`

```php
isCalledOn(string $method, int $callNumber): AssertMock
```

The n-th call (1-based) across all methods was this one. On the mock, that order spans all instances; on an instance, only its own calls.

```php
AssertMock::begin($connection)
    ->isCalledOn('beginTransaction', 1)
    ->isCalledOn('insert', 2)
    ->isCalledOn('commit', 3);
```

### Grouping: `checkMethod`

```php
checkMethod(string $method, callable $assertions): AssertMock
```

Groups several checks about one method. The callback receives an `AssertMockMethod`, which has every method above without the `$method` parameter.

```php
use MicroUnit\Assertion\AssertMockMethod;

AssertMock::begin($mailer)->checkMethod('send', function (AssertMockMethod $send) {
    $send->isCalledTimes(2)
        ->isCalledWith(['ada@example.com', 'Welcome'])
        ->isCalledOn(2);
});
```

Each `AssertMock::begin()` counts as one assertion for [risky test detection](assertions.md#every-assertion-counts). Unlike `Assert`, mock assertions take no custom message.

## Mocks as fixtures

When several tests need the same collaborator, declare it as a fixture. Fixtures are created fresh for every test, so each test gets its own mock with clean call logs, and can change what it returns without affecting the others:

```php
$tester->fixture('mailer', fn () => MockBuilder::create(Mailer::class)
    ->returns('send', true)
    ->build()
    ->newInstance());

$tester->define('sends_a_welcome_mail', function (Mailer $mailer) {
    (new Signup($mailer))->register('ada@example.com');

    AssertMock::begin($mailer)->isOnlyCalledWith('send', ['ada@example.com', 'Welcome']);
});

$tester->define('reports_a_failed_mail', function (Mailer $mailer) {
    MicroMock::of($mailer)->returns('send', false);

    $mailSent = (new Signup($mailer))->register('ada@example.com');

    Assert::isFalse($mailSent);
});
```

Return the instance from the fixture, not the `MicroMock`: the instance is what the code under test needs, and `AssertMock::begin()` and `MicroMock::of()` both accept it.

Avoid `shared: true` for mocks; a shared mock keeps its configuration and call log across tests, and then the call counts of one test depend on the tests before it. Fixtures are explained in [writing tests](writing-tests.md).

## What cannot be mocked

A mock is a generated subclass, so it is bound by what PHP lets a subclass do:

- **Final classes and enums** cannot be mocked at all. `build()` throws `Cannot mock Signup: it is final.` Mock an interface the class implements, or extract one.
- **Final methods** run their real code and are not recorded. Configuring one throws.
- **Static methods** run their real code and are not recorded. Configuring one throws. An abstract static method gets a stub that throws a `MockException` when called.
- **Private methods** are invisible to a subclass. Configuring one throws. They run as part of whatever public method calls them, which is usually what you want anyway.
- **Constructors, destructors, `__clone` and the serialisation hooks** are never intercepted.

Magic methods through `__call()` do work: on a class with `__call()`, `returns('findByEmail', ...)` configures the magic method, and calls are recorded under the name that was called.

If you find yourself fighting these limits, the class is telling you something about its design. A static call or a `new` inside a method is a dependency you cannot replace in a test; injecting it makes the code easier to test and usually easier to change.

## Coming from PHPUnit mocks

`microunit migrate` translates most PHPUnit mock code automatically (see [migrating from PHPUnit](migrating-from-phpunit.md)). For writing new tests, this is the mapping:

| PHPUnit | MicroUnit |
| --- | --- |
| `$this->createMock(Foo::class)` / `createStub()` | `MockBuilder::create(Foo::class)->build()->newInstance()` |
| `->method('x')->willReturn($v)` | `->returns('x', $v)` |
| `->willReturn($a, $b)` / `willReturnOnConsecutiveCalls($a, $b)` | `->returnsSequence('x', $a, $b)` |
| `->willReturnCallback($fn)` | `->returnsCallback('x', $fn)` |
| `->willReturnMap($map)` | `->returnsMap('x', $map)` |
| `->willThrowException($e)` | `->throws('x', $e)` |
| `->willReturnSelf()` | nothing for `static`/`self` return types; else `->returnsCallback('x', fn () => $instance)` |
| `->willReturnArgument(0)` | `->returnsCallback('x', fn (...$args) => $args[0])` |
| `$this->createPartialMock(Foo::class, ['x'])` | `MockBuilder::create(Foo::class)->interceptOnly('x')` |
| `getMockBuilder(Foo::class)->disableOriginalConstructor()` | `->disableOriginalConstructor()` |
| `->setConstructorArgs([...])` | `->withConstructorArgs([...])` |
| `->expects($this->once())` | `AssertMock::begin($mock)->isCalledOnce('x')` after the code ran |
| `->expects($this->never())` | `->isNotCalled('x')` |
| `->expects($this->exactly(3))` | `->isCalledTimes('x', 3)` |
| `->expects($this->atLeastOnce())` | `->isCalled('x')` |
| `->with('a', 'b')` | `->isCalledWith('x', ['a', 'b'])` |
| `->with($this->callback($fn))` | `->isOnlyCalledWithMatchingArgs('x', $fn)` |
| `->withConsecutive(...)` (removed in PHPUnit 10) | `->isCalledWithOnSpecificCall('x', [...], $n)` per call |

The biggest change is where expectations live. In PHPUnit, `expects()` and `with()` sit in the arrange step and are verified silently when the test ends. In MicroUnit, the arrange step only says what the mock returns, and the checks come after the code ran, where a reader expects assertions to be. One side effect I like: a mock that is only used as a stub needs no extra code at all, and a test that never gets around to checking anything is flagged as risky instead of passing quietly.
