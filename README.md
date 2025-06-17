# MicroUnit

**Minimal. Modern. Mighty.**  
_The next-generation PHP unit testing framework for developers who want speed, clarity, and power._

---

## What is MicroUnit?

MicroUnit is a modern, high-performance unit testing framework for PHP 8+. It’s designed for developers who want expressive, maintainable tests—without the bloat, legacy baggage, or steep learning curve of traditional frameworks. MicroUnit is code-first, configuration-light, and built for modern PHP projects of any size.

---

## Why Choose MicroUnit?

MicroUnit isn’t just another testing framework—it’s a fresh take on what PHP testing should be. If you’re tired of slow test suites, cryptic configuration files, and frameworks that feel stuck in the past, MicroUnit is for you.

- **Lightning Fast:** MicroUnit’s core is engineered for speed. Tests start instantly, and results are delivered in real time.
- **Zero Bloat:** No unnecessary dependencies, no legacy code, no slowdowns. MicroUnit is as lean as it gets.
- **Modern PHP:** Built from the ground up for PHP 8+, MicroUnit leverages the latest language features for type safety and expressiveness.
- **Developer Experience First:** Clear error messages, beautiful diffs, and a fluent API make testing a joy, not a chore.
- **All-in-One:** Mocking, assertions, setup/teardown, and output customization are all built in—no plugins required.
- **Effortless Integration:** Works seamlessly with any CI/CD pipeline and outputs results in multiple formats.

---

## Feature Overview

### 🚀 Ultra-Fast Test Discovery & Execution

MicroUnit uses smart, recursive test discovery to find and run your tests in a flash. There’s no waiting for autoloaders or parsing slow configuration files. Just point MicroUnit at your test directory and go.

### 🧪 Expressive, Fluent Assertions

MicroUnit’s assertion library is designed to be both powerful and readable. Write assertions that make your intent clear, with detailed error reporting and type safety. Whether you’re testing values, arrays, or objects, MicroUnit’s assertions have you covered.

### 🧙‍♂️ Built-in Mocking

No need for third-party libraries or complicated setup. MicroUnit’s mocking system lets you create mocks, stubs, and spies with a simple, intuitive API. Define return values, throw exceptions, and verify interactions—all in a few lines of code.

### 📝 Flexible Output Writers

Choose from minimal, detailed, or file-based reporters out of the box. **You can configure multiple test writers at once**—for example, outputting results to the console and saving them to a file simultaneously. This makes it easy to tailor your reporting for both local development and CI/CD pipelines.

**Want even more control?**  
MicroUnit is fully extensible: **You can write your own custom test writers** to output results in any format you need (HTML, JSON, Slack notifications, dashboards, etc.).

### 🔧 Simple, Code-First Configuration

Forget about XML or YAML. MicroUnit is configured entirely in PHP, making your setup explicit, versionable, and easy to understand. Add or remove features as you need, with no hidden magic.

### 🛠️ Setup & Teardown

MicroUnit supports per-suite and per-test setup and teardown hooks, so you can prepare your environment and clean up after tests with ease. Pass setup results directly to your tests for maximum flexibility.

### 📦 Zero-Dependency, Composer-Friendly

Install MicroUnit with Composer and get started in seconds. No global installs, no vendor lock-in, and no unnecessary dependencies.

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

**Why MicroUnit Outshines the Rest:**

- **No legacy baggage:** Unlike PHPUnit and Codeception, MicroUnit is built from scratch for PHP 8+.
- **No plugins required:** Mocking and fluent assertions are built-in, not bolted on.
- **No configuration headaches:** Everything is PHP code—no XML, no YAML, no magic.
- **Minimalist core:** Focus on what matters—writing and running tests, not managing dependencies.

---

## Getting Started

### 1. Install

```sh
composer require --dev your-vendor/microunit
```

### 2. Configure

Create `microunit.config.php`:

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

## Defining Tests

You can define individual tests using the `define` method:

```php
$tester->define('test_addition', function($a, $b) {
    Assert::equals($a + $b, 4);
}, 2, 2);
```

- The first argument is the test name.
- The second argument is a function that contains your test logic.
- Any additional arguments are passed to the test function.

### Defining Groups of Tests

To define a group of related tests, use `defineGroup`:

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
- The third argument is an array of `TestCase` objects, each with a name and arguments.
- This creates tests named `addition_case1`, `addition_case2`, etc.

Refer to the documentation for more details on

---

## Feature Deep Dive & Examples

### 🚀 Ultra-Fast Test Discovery & Execution

MicroUnit scans your test directories recursively and runs only the tests you need. No more waiting for slow test runners or dealing with outdated caches.

```php
// microunit.config.php
return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->build();
```

---

### 🧪 Expressive, Fluent Assertions

MicroUnit’s assertion library is designed for clarity and power. Here’s how you can use it:

#### Basic Assertions

```php
use MicroUnit\Assertion\Assert;

Assert::equals(4, 2 + 2);
Assert::notEquals(5, 2 + 2);
Assert::isTrue(true);
Assert::isNull(null);
Assert::throws(fn() => 1 / 0, DivisionByZeroError::class);
```

#### Fluent Assertions

```php
use MicroUnit\Assertion\AssertSingle;

AssertSingle::begin($result)
    ->equals(42)
    ->notNull()
    ->isInt();
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

### 🧙‍♂️ Built-in Mocking

Mocks are first-class citizens in MicroUnit. Create, configure, and verify mocks with ease:

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

#### Advanced Mocking

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

### 📝 Flexible Output Writers

MicroUnit lets you choose how your test results are displayed or stored. **You can configure multiple test writers at once**—for example, outputting results to the console and saving them to a file simultaneously. This makes it easy to tailor your reporting for both local development and CI/CD pipelines.

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

**Explanation:**

- You can add as many test writers as you need using `addTestWriter()`.
- Each writer will receive the test results, allowing you to combine real-time console output with persistent logs or custom formats.
- Built-in writers include minimal, detailed, and file-based options.

#### ✨ Writing Your Own Test Writers

MicroUnit is fully extensible: **You can write your own custom test writers** to output results in any format you need (HTML, JSON, Slack notifications, dashboards, etc.).

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

Then add your writer in the config:

```php
return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->addTestWriter(new MyCustomTestWriter())
    ->build();
```

See [`src/Output/MinimalStringTestWriter.php`](src/Output/MinimalStringTestWriter.php) or [`src/Output/FileTestWriter.php`](src/Output/FileTestWriter.php) for real examples.

---

### 🔧 Simple, Code-First Configuration

All configuration is done in PHP, making it easy to version, review, and modify:

```php
return MicroUnitConfigBuilder::create()
    ->withTestDir('./tests')
    ->addTestWriter(new MinimalStringTestWriter())
    ->build();
```

---

### 🛠️ Setup & Teardown

Prepare your environment and clean up after tests with simple hooks.

**If you use `setUp`, the value you return will be passed as the first argument to every test in that suite.**

#### How to Use the Setup Result in Tests

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

- **Fast, clear output:** See results instantly.
- **Fails fast:** Get detailed error messages and diffs.
- **CI/CD ready:** Integrates with any pipeline.

---

## Contributing

We welcome issues, PRs, and feature requests! See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## License

See [LICENSE](LICENSE).

---

## Ready to Ditch the Bloat?

**MicroUnit** is for developers who want to test smarter, not harder.  
No legacy, no plugins, no config files—just pure, modern PHP testing.

**Try MicroUnit today and experience the difference!**

---

**Minimal. Modern. Mighty. — MicroUnit**
