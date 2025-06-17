# MicroUnit

**Minimal. Modern. Mighty.**  
_The next-generation PHP unit testing framework for developers who want speed, clarity, and power._

---

[![License](https://img.shields.io/badge/license-Custom-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://www.php.net/)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg)](#)

---

## What is MicroUnit?

MicroUnit is a modern, high-performance unit testing framework for PHP 8+. It’s designed for developers who want expressive, maintainable tests—without the bloat, legacy baggage, or steep learning curve of traditional frameworks. MicroUnit is code-first, configuration-light, and built for modern PHP projects of any size.

---

## Why Choose MicroUnit?

MicroUnit isn’t just another testing framework—it’s a fresh take on what PHP testing should be. If you’re tired of slow test suites, cryptic configuration files, and frameworks that feel stuck in the past, MicroUnit is for you.

- **Lightning Fast:** Smart, recursive test discovery and instant execution.
- **Zero Bloat:** No unnecessary dependencies or legacy code.
- **Modern PHP:** Built for PHP 8+, leveraging the latest language features.
- **Developer Experience First:** Clear error messages, beautiful diffs, and a fluent API.
- **All-in-One:** Mocking, assertions, setup/teardown, and output customization built-in.
- **Effortless Integration:** Seamless CI/CD support and multiple output formats.
- **Extensible:** Easily add your own custom test writers and assertions.

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

Choose from minimal, detailed, or file-based reporters out of the box. **You can configure multiple test writers at once**—for example, outputting results to the console and saving them to a file simultaneously. This makes it easy to tailor your reporting for both local development and CI/CD pipelines.

**Want even more control?**  
MicroUnit is fully extensible: **You can write your own custom test writers** to output results in any format you need (HTML, JSON, Slack notifications, dashboards, etc.).

### 🔧 Simple, Code-First Configuration

Forget about XML, JSON or YAML. Configure everything entirely in PHP, making your setup explicit, versionable, and easy to understand with no hidden magic.

### 🛠️ Setup & Teardown

Per-suite and per-test setup/teardown hooks. Pass setup results directly to your tests.

### 📦 Zero-Dependency, Composer-Friendly

Install with Composer and get started in seconds.

---

## MicroUnit vs. Popular Alternatives

| Feature              | MicroUnit     | PHPUnit       | Pest        | Codeception |
| -------------------- | ------------- | ------------- | ----------- | ----------- |
| Modern PHP Support   | ✅ (PHP 8+)   | ⚠️ (Legacy)   | ✅          | ⚠️          |
| Fluent Assertions    | ✅            | ⚠️            | ✅          | ⚠️          |
| Built-in Mocking     | ✅            | ⚠️ (Separate) | ⚠️ (Plugin) | ⚠️ (Plugin) |
| Config in PHP        | ✅            | ❌ (XML)      | ✅          | ⚠️          |
| Output Customization | ✅            | ⚠️            | ⚠️          | ⚠️          |
| Zero Bloat           | ✅            | ❌            | ⚠️          | ❌          |
| Fast Startup         | ✅            | ❌            | ✅          | ❌          |
| Test Discovery       | ✅ (Glob/Rec) | ✅            | ✅          | ✅          |
| Extensible           | ✅            | ✅            | ✅          | ✅          |

---

## Requirements

- **PHP 8.0 or higher**
- Composer

---

## Getting Started

### 1. Install

```sh
composer require --dev your-vendor/microunit
```

### 2. Configure

Create `microunit.config.php` in your project root:

```php
<?php
use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\MinimalStringTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->addTestWriter(new MinimalStringTestWriter())
    ->build();
```

---

## Writing Tests

### Defining Individual Tests

```php
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

```php
use MicroUnit\Assertion\AssertSingle;

AssertSingle::begin($result)
    ->equals(42)
    ->notNull()
    ->isInt();
```

### Array Assertions

```php
use MicroUnit\Assertion\AssertArray;

AssertArray::begin([1, 2, 3])
    ->contains(2)
    ->countEquals(3)
    ->notEmpty()
    ->hasKey(0);
```

### Numeric Assertions

```php
use MicroUnit\Assertion\AssertNumeric;

AssertNumeric::begin(10)
    ->isGreaterThan(5)
    ->isLessThan(20)
    ->isBetween(5, 15);
```

---

## Mocking

### Basic Mocking

```php
use MicroUnit\Mock\MockBuilder;
use MicroUnit\Assertion\AssertMock;

$mock = MockBuilder::create(MyService::class)
    ->returns('getValue', 123)
    ->build();

$instance = $mock->newInstance();
$instance->getValue(); // returns 123

AssertMock::begin($mock)
    ->isCalledOnce('getValue');
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

AssertMock::begin($mock)
    ->isCalledTimes('getValue', 3)
    ->isNotCalled('failMethod');
```

---

## Output Writers

MicroUnit lets you choose how your test results are displayed or stored. **You can configure multiple test writers at once**—for example, outputting results to the console and saving them to a file simultaneously. This makes it easy to tailor your reporting for both local development and CI/CD pipelines.

```php
use MicroUnit\Output\MinimalStringTestWriter;
use MicroUnit\Output\FileTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->addTestWriter(new MinimalStringTestWriter())
    ->addTestWriter(new FileTestWriter('./test-results.log'))
    ->build();
```

### Custom Output Writers

Implement the [`MicroUnit\Output\ITestWriter`](src/Output/ITestWriter.php) interface:

```php
use MicroUnit\Output\ITestWriter;
use MicroUnit\Core\TestResult;

class MyCustomTestWriter implements ITestWriter
{
    public function writeResult(TestResult $result): void { /* ... */ }
    public function writeResults(array $results): void { /* ... */ }
    public function writeSummary(int $totalTests, int $successes, int $failures): void { /* ... */ }
    public function writeSuite(string $suite): void { /* ... */ }
}
```

See [src/Output/MinimalStringTestWriter.php](src/Output/MinimalStringTestWriter.php) or [src/Output/FileTestWriter.php](src/Output/FileTestWriter.php) for examples.

---

## Setup & Teardown

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

Run your tests with a single command:

```sh
php bin/run-tests.php
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
    Core/
    Exceptions/
    Helpers/
    Mock/
    Output/
    Setup/
tests/
    microunit.config.php
    ...
bin/
    run-tests.php
    run_logs/
vendor/
    ...
```

---

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

## Ready to Ditch the Bloat?

**MicroUnit** is for developers who want to test smarter, not harder.  
No legacy, no plugins, no config files—just pure, modern PHP testing.

**Try MicroUnit today and experience the difference!**

---

**Minimal. Modern. Mighty. — MicroUnit**
