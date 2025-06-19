---
layout: default
---

**[← Back to Home](/)**

# Configuration

MicroUnit can be configured via a builder pattern using a php config file.
Each configuration method is listed below with its signature and a concise explanation.

---

## Creating the Config file

Create a file called `microunit.config.php` in the current working directory or any parent directories.

MicroUnit will discover that file and cache it's path for optimal performance. If you happen to move the file the cache will automatically be invalidated and the new file path will be cached.

The config file can contain any PHP logic you want as long as it ends up returning an instance of `MicroUnit\Config\MicroUnitConfig`.

While you can use create an instance manually through calling
`new MicroUnit\Config\MicroUnitConfig(...)` and passing the desired constructor arguments using the `MicroUnitConfigBuilder` is recommended (FQN: MicroUnit\Config\MicroUnitConfigBulder).

## MicroUnitConfigBuilder Methods

### `withTestDir(string $dir)`

Sets the directory where test files are located.

> **🚨 WARNING: DO NOT USE `__DIR__`**  
> Using `__DIR__` to construct a test directory path breaks test discovery and will throw an exception.  
> Use a relative path from the location of `microunit.config.php` instead.

```php
MicroUnitConfigBuilder()::create()
    ->withTestDir(__DIR__ . '/tests')
```

### `withTestWriters(ITestWriter ...$writers)`

Sets one or more output writers for test results.

> **Note:** Calling this method multiple times will override the output writers each time

```php
MicroUnitConfigBuilder()::create()
    ->withTestWriters(
        new MinimalStringTestWriter(),
        new FileTestWriter('/tmp/results.log')
    )
```

### `addTestWriter(ITestWriter $writer)`

Adds an output writer for test results.

> **Note:** Unlike `withTestWriters` this method will **NOT** override the previously defined output writers it just adds a new output writer to the list with each call.

Calling this method two times and calling `withTestWriters(ITestWriter ...$writers)` with two (or more) arguments behaves the same it just comes down to preference.

> Note: The TestWriters are it's own thing and the logic internally works different so you **should** be using `__DIR__` here.

```php
MicroUnitConfigBuilder()::create()
    ->addTestWriter(new MinimalStringTestWriter())
    ->addTestWriter(, new FileTestWriter(__DIR__ . '/tmp/results.log'))
```

### `withTestFilePatterns(string ...$patterns)`

Sets the glob patterns for test file names. These are going to be used in test discovery to find the test files to run.

> **Note:** Calling this method multiple times will override the previous patterns each time.

```php
MicroUnitConfigBuilder()::create()
    ->withTestFilePatterns('*-tests.php', 'Test[A-Z]*.php')
```

### `addTestFilePattern(string $pattern)`

Adds a glob pattern for test file names. These are going to be used in test discovery to find the test files to run.

> **Note:** Unlike `withTestFilePatterns` this method will **NOT** override the previously defined patterns when called multiple times. It just adds a new pattern to the list with each call.

Calling this method two times and calling `withTestFilePatterns(string ...$patterns)` with two (or more) arguments behaves the same it just comes down to preference.

```php
MicroUnitConfigBuilder()::create()
    ->addTestFilePattern('*-tests.php')
    ->addTestFilePattern('Test[A-Z]*.php')
```

### `stopOnFailure()`

Stops test execution after the first failure.

```php
MicroUnitConfigBuilder()::create()
    ->stopOnFailure()
```

### `withBootstrapFile(string $file)`

Specifies a PHP file to execute before running tests.

> **🚨 WARNING: DO NOT USE `__DIR__`**  
> Using `__DIR__` to construct the path to the bootstrap file will throw an exception.  
> Use a relative path from the location of `microunit.config.php` instead.

```php
MicroUnitConfigBuilder()::create()
    ->withBootstrapFile('/bootstrap.php') // DONT USE __DIR__
```

### `persistRunLogs()`

Enables persistent logging of test runs.

When this method is called in configuration the run logs of your previous test runs are not going to be deleted.

> Note: When removed from the configuration all previously persisted run logs contained in the folder `microunit/bin/run_logs` are going to be deleted. So make sure you safe them somewhere else if you are looking to keep them.

```php
MicroUnitConfigBuilder()::create()
    ->persistRunLogs()
```

### `build()`

Finalizes and returns the configuration object.

```php
MicroUnitConfigBuilder()::create()
    ->build()
```

---

## Example: Full Configuration

```php
use MicroUnit\Config\MicroUnitConfigBuilder;
use MicroUnit\Output\MinimalStringTestWriter;
use MicroUnit\Output\FileTestWriter;

return MicroUnitConfigBuilder::create()
    ->withTestDir(__DIR__ . '/tests')
    ->withTestWriters(
        new MinimalStringTestWriter(),
        new FileTestWriter('/tmp/results.log')
    )
    ->withTestFilePatterns('*-tests.php')
    ->stopOnFailure()
    ->withBootstrapFile(__DIR__ . '/bootstrap.php')
    ->persistRunLogs()
    ->build();
```

---

**[⬆ Back to Top](#table-of-contents)** • **[📘 Home](/)**
