![MicroUnit Banner](images//microunit_banner_wide.png "MicroUnit")

# MicroUnit

**Minimal. Modern. Mighty.**  
_The next-generation PHP unit testing framework for developers who want speed, clarity, and power._

See [Full Documentation](https://mitarnik04.github.io/MicroUnit/) for an in-depth explanation on all features provided by MicroUnit.

---

[![License Apache2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://www.php.net/)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg)](#)
[![Beta](https://img.shields.io/badge/status-beta-yellow.svg)](#)
[![Sponsor](https://img.shields.io/github/sponsors/mitarnik04?label=Sponsor&logo=GitHub)](https://github.com/sponsors/mitarnik04)

---

> ⚠️ **MicroUnit is currently in public beta!**  
> Features may change and bugs may exist. Feedback is welcome as we prepare for the first stable release.

## What is MicroUnit?

MicroUnit is a modern, high-performance unit testing framework for PHP 8+. It’s designed for developers who want expressive, maintainable tests, without the bloat, legacy baggage, or steep learning curve of traditional frameworks. MicroUnit is code-first, configuration-light, and built for modern PHP projects of any size.

---

## Why Choose MicroUnit?

MicroUnit tries to provide a different take on Unit Testing. Giving the user more freedom in choosing the way his tests outputs look and even giving a couple options in how you want to write your tests while still providing all the crucial features you need in a Unit Testing Framework. The focus clearly lies on simplicity, speed and a zero dependency philosophy without loosing out on functionality.

---

## Features

### 🚀 Ultra-Fast Test Discovery & Execution

MicroUnit recursively scans your test directories and runs your tests with minimal startup time. No slow autoloaders or heavy configuration.

### 🧪 Expressive, Fluent Assertions

The fluent assertion library is designed to be both powerful and readable. Write assertions that make your intent clear, with detailed error reporting and type safety. Whether you’re testing values, arrays, or objects, MicroUnit’s assertions have you covered.

### 🧙‍♂️ Built-in Mocking

No need for third-party libraries, plugins or complicated setup.  
Create mocks, stubs, and spies with a simple, intuitive API. Define return values, throw exceptions, and verify interactions.

### 📝 Flexible Output Writers

Choose from minimal, detailed, or file-based reporters out of the box. **You can configure multiple test writers at once** for example, outputting results to the console and saving them to a file simultaneously. This makes it easy to tailor your reporting for both local development and CI/CD pipelines.

**Want even more control?**  
MicroUnit is fully extensible: **You can write your own custom test writers** to output results in any format you need (HTML, JSON, Slack notifications, dashboards, etc.).

### 🔧 Simple, Code-First Configuration

Forget about XML, JSON or YAML. Configure everything entirely in PHP, making your setup explicit, versionable, and easy to understand with no hidden magic.

### 🛠️ Setup & Teardown

Per-suite and per-test setup/teardown hooks. Pass setup results directly to your tests.

### 📦 Zero-Dependency, Composer-Friendly

Install with Composer and get started in seconds.

---

## Requirements

- **PHP 8.0 or higher**
- Composer

---

## Getting Started

### 1. Install

```sh
composer require --dev microunit/microunit
```

### 2. Configure

Create `microunit.config.php` in your current working directory or any parent directories.

See [Configuration](https://mitarnik04.github.io/MicroUnit/configuration) for a full list of configuration options

> Note: All paths are going to be resolved relative to the location of your config file.

```php
<?php
use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\MinimalStringTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->addTestFilePattern('*Test.php') // If not configured '*-tests.php' will be used
    ->addTestWriter(new MinimalStringTestWriter()) //If not configured MinimalStringTestWriter will be used
    ->build();
```

---

## Writing Tests

See [Usage](https://mitarnik04.github.io/MicroUnit/usage) for a more detailed and in-depth explanation on how to start writing your first tests.

### Setup

After you have defined your target test-directory and your testFile patterns you can create your test files.  
For the configuration above we would create a directory called `tests` and let's say we create a file `tests/ExampleTest.php` to match our defined test file pattern above.

The First thing we have to do in our test file is get a Tester instance. Each tester is tied to a Testsuite so you have to specify that when calling `TestSetup::getTester($testSuite)`

```php
<?php

use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester("YOUR_TEST_SUITE_NAME");
```

After that the `$tester` instance can be used to define tests inside that suite (see following sections).

### Defining Individual Tests

```php
use MicroUnit\Assertion\Assert;

$tester->define('test_addition', function($a, $b) {
    Assert::equals($a + $b, 4);
}, 2, 2);
```

- The first argument is the test name.
- The second argument is a function containing your test logic.
- Additional arguments are passed to the test function.

### Defining Groups of Tests

```php
$cases = [
    new TestCase('case1', [2, 2]),
    new TestCase('case2', [3, 1]),
];

$tester->defineGroup('addition', function($a, $b) {
    Assert::equals($a + $b, 4);
}, $cases);
```

- The first argument is the base name for the group.
- The second argument is the test function.
- The third argument is an array of `TestCase` objects.

---

## Assertions

See [Assertions](https://mitarnik04.github.io/MicroUnit/assertions) for a complete list of the available assertion methods and a more detailed explanation.

### Basic Assertions

```php
use MicroUnit\Assertion\Assert;

Assert::equals(4, 2 + 2);
Assert::notEquals(5, 2 + 2);
Assert::isTrue(true);
Assert::isNull(null);
Assert::throws(fn() => 1 / 0, DivisionByZeroError::class);
```

### Fluent Assertions

While the Assert class supports all below assertion methods by just calling them statically they are grouped into different kinds of assertions when using the fluent assertions api.

#### Single Value Assertions

```php
use MicroUnit\Assertion\AssertSingle;

AssertSingle::begin($result)
    ->equals(42)
    ->notNull()
    ->instanceOf(int::class);
```

#### Array Assertions

```php
use MicroUnit\Assertion\AssertArray;

AssertArray::begin([1, 2, 3])
    ->contains(2)
    ->countEquals(3)
    ->notEmpty()
    ->hasKey(0);
```

#### Numeric Assertions

```php
use MicroUnit\Assertion\AssertNumeric;

AssertNumeric::begin(10)
    ->isGreaterThan(5)
    ->isLessThan(20)
    ->isBetween(5, 15);
```

---

## Mocking

See [Mocking](https://mitarnik04.github.io/MicroUnit/Mocking) for a full list of available builder methods and a more detailed explanation.

### Basic Mocking

```php
use MicroUnit\Mock\MockBuilder;
use MicroUnit\Assertion\AssertMock;

$mock = MockBuilder::create(MyService::class)
    ->returns('getValue', 123)
    ->build();

$instance = $mock->newInstance();
$instance->getValue(); // returns 123

```

### Advanced Mocking

```php
$mock = MockBuilder::create(MyService::class)
    ->returnsSequence('getValue', 1, 2, 3)
    ->throws('failMethod', new Exception('fail!'))
    ->build();

$instance = $mock->newInstance();
$instance->getValue(); // 1
$instance->getValue(); // 2
$instance->getValue(); // 3

```

### Asserting Mocks

You can assert different things on Mocks using the dedicated [`MicroUnit\Assertion\AssertMock`](src/Assertion/AssertMock.php) class.

> Note: Since mocks require some extra logic under the hood to assert you can not assert them through static methods via the [`MicroUnit\Assertion\Assert`](src/Assertion/Assert.php) class.

Looking at the provided example above let's add some Assertion logic to it.

```php
$mock = MockBuilder::create(MyService::class)
    ->returnsSequence('getValue', 1, 2, 3)
    ->throws('failMethod', new Exception('fail!'))
    ->build();

$instance = $mock->newInstance();
$instance->getValue(); // 1
$instance->getValue(); // 2
$instance->getValue(); // 3

//Assertion
AssertMock::begin($mock)
    ->isCalledTimes('getValue', 3)
    ->isNotCalled('failMethod');
```

---

## Output Writers

MicroUnit lets you choose how your test results are displayed or stored. **You can configure multiple test writers at once** for example, outputting results to the console and saving them to a file simultaneously. This makes it easy to tailor your reporting for both local development and CI/CD pipelines.

See [Output](https://mitarnik04.github.io/MicroUnit/output) for a more detailed explanation.

```php
use MicroUnit\Output\MinimalStringTestWriter;
use MicroUnit\Output\FileTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    // Output results to the console
    ->addTestWriter(new MinimalStringTestWriter())
    // Also write results to a file
    ->addTestWriter(new FileTestWriter('./test-results.log'))
    ->build();
```

### Custom Output Writers

To create a custom test writer, implement the [`MicroUnit\Output\ITestWriter`](src/Output/ITestWriter.php) interface.  
You **must** implement the following methods:

```php
use MicroUnit\Output\ITestWriter;
use MicroUnit\Core\TestResult;

class MyCustomTestWriter implements ITestWriter
{
    public function writeResult(TestResult $result): void
    {
        // Output a single test result
    }

    public function writeResults(array $results): void
    {
        // Output all results for a suite
    }

    public function writeSummary(int $totalTests, int $successes, int $failures): void
    {
        // Output a summary at the end
    }

    public function writeSuite(string $suite): void
    {
        // Output when a suite starts
    }
}
```

See [src/Output/MinimalStringTestWriter.php](src/Output/MinimalStringTestWriter.php) or [src/Output/FileTestWriter.php](src/Output/FileTestWriter.php) for examples.

---

## Setup & Teardown

See [Usage &rarr; Lifecycle-Hooks](https://mitarnik04.github.io/MicroUnit/usage#lifecycle-hooks) for a more detailed explanation on Setup & Teardown

Use `setUp` and `tearDown` hooks for per-test preparation and cleanup.

```php
$tester->setUp(function () {
    // This runs before each test
    // Return any value you want to share with your test
    return new DatabaseConnection();
});

$tester->tearDown(function ($testResult, $db) {
    // This runs after each test
    $db->close();
});

// Each test must accept the setup result as its first argument!
$tester->define('fetch user', function ($db) {
    $user = $db->fetchUser(1);
    Assert::notNull($user);
});
```

#### Limitations

- **If you use `setUp`, every test function should accept the setup result as its first parameter.**  
  If you forget, PHP will throw an argument count error.
- **If your test function does not have any arguments at all and you use `setUp`, that's OK as well.**  
  MicroUnit will simply not pass the setup value to that test.
- **If you use grouped/parameterized tests, the setup result will be the first argument, followed by your test case parameters:**

```php
$tester->defineGroup('math', function ($setupResult, $a, $b, $expected) {
    // $setupResult is from setUp()
    Assert::equals($expected, $a + $b);
}, [
    new TestCase('add1', [1, 2, 3]),
    new TestCase('add2', [2, 2, 4]),
]);
```

- **If you don’t use `setUp`, your test functions can have any signature you want.**

---

**Summary:**  
When using `setUp`, always remember:

- The return value from `setUp` is injected as the first argument to every test in the suite (unless your test function has no parameters).
- Adjust your test function signatures accordingly.

---

## MicroUnit in Action

See [Usage &rarr; Running Tests](https://mitarnik04.github.io/MicroUnit/usage#running-tests) for a more detailed explanation on how to run your MicroUnit tests.

Run your tests with a single command:

```sh
.\vendor\bin\microunit
```

- Fast, clear output.
- Fails fast with detailed error messages and diffs.
- CI/CD ready.

---

## Directory Structure

```
src/
    Assertion/
    Bootstrap/
    Cache/
    Config/
    Core/           # contains the core logic that is needed to run unit tests
    Exceptions/
    Helpers/        # contains a set of internal helpers
    Mock/
    Output/
    Setup/
    Toolkit/        # contains a set of useful tools for writing unit tests
bin/
    run-tests.php
    run_logs/
```

---

MicroUnit isn’t just another testing framework, it’s a fresh take on what PHP testing should be. If you’re tired of slow test suites, cryptic configuration files, and frameworks that feel stuck in the past, MicroUnit is for you.

- **Lightning Fast:** Smart, recursive test discovery and instant execution.
- **Zero Bloat:** No unnecessary dependencies or legacy code.
- **Modern PHP:** Built for PHP 8+, leveraging the latest language features.
- **Developer Experience First:** Clear error messages, beautiful diffs, and a fluent API.
- **All-in-One:** Mocking, assertions, setup/teardown, and output customization built-in.
- **Effortless Integration:** Seamless CI/CD support and multiple output formats.
- **Extensible:** Easily add your own custom test writers and assertions.

---

## Become a Sponsor

MicroUnit isn’t just another testing tool, it’s the foundation for serious, modern PHP testing. No matter if you are building a professional-grade software or just a small library with PHP 8+, MicroUnit is built for your stack, your speed, and your standards.

By sponsoring MicroUnit, you're investing in the future of PHP development: faster test cycles, clearer code, and a cleaner ecosystem, without the legacy baggage.

🙌 Keep MicroUnit free, open-source, and evolving at full speed.

📢 Get recognized on our GitHub and website as a key supporter

🧑‍💻 Help shape the roadmap and gain direct influence on upcoming features

❤️ Be part of a growing movement to modernize PHP for the next generation

Ready to lead the charge?  
I have finally managed to publish my **github sponsors** profile so donations can now be made through there!  
[![Sponsor](https://img.shields.io/github/sponsors/mitarnik04?label=Sponsor&logo=GitHub)](https://github.com/sponsors/mitarnik04)

If you prefer a one-time contribution, you can also buy me a coffee &rarr; MicroUnit runs on PHP, but the maintainer runs on caffeine.  
[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-orange?style=for-the-badge&logo=buy-me-a-coffee&logoColor=white)](https://coff.ee/mitarnikolic)

**Any donation is very much appreciated and will be listed on the [official documentation site](https://mitarnik04.github.io/MicroUnit)**.

---

## Upcoming Features

Listed below are the features for MicroUnit that are on the roadmap for being released in the upcoming versions.

### Support for parallel unit testing

Currently MicroUnit only supports sequential testing. While this is still very fast we are aiming to provide support for parallel unit testing to make test execution even faster in places where you might need it.

### Code Coverage

A very useful feature in other popular unit testing frameworks is the ability to generate code coverage reports so you always know how much of your code you have covered with your tests.  
That's why we are aiming to bring that feature to MicroUnit as well.

## Contributing

We welcome issues, pull requests, and feature suggestions!  
Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## License

See [LICENSE](LICENSE).

---

## Security

If you discover a security vulnerability, please report it privately.

---

## Contact

For questions or support, open an issue or contact the maintainers.

---

## Do you enjoy this take on Unit Tests?

**MicroUnit** is for developers who want to test smarter, not harder.  
No legacy, no plugins, no config files &rarr; just pure, modern PHP testing.

**Try MicroUnit today and experience the difference!**

---

**Minimal. Modern. Mighty &rarr; MicroUnit**
