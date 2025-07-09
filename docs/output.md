---
layout: default
---

**[← Back to Home](index.md)**

# Output

MicroUnit supports multiple output writers.  
Each writer and its methods are listed below with a concise explanation.

One or more output writers can be passed to the microunit configuration (`microunit.config.php`) to **define how the test results should be outputted**. See [Configuration: withTestWriters](configuration.md#withtestwritersitestwriter-writers) and [Configuration: addTestWriter](configuration.md#addtestwriteritestwriter-writer) for more details.

---

## Built-in Writers

### `MinimalStringTestWriter`

Minimal console output for test results.

### `StringTestWriter`

Detailed console output for test results.

### `FileTestWriter($filePath)`

Writes test results to the specified file.

```php
new FileTestWriter(__DIR__ . '/tmp/results.log')
```

---

## Custom Writers

Implement the `ITestWriter` interface with the methods below to create a custom writer.

Your custom output writer can then be passed to the configuration just like the build in ones.

### `writeResult(TestResult $result)`

Writes a single test result.

#### When is it called by the MicroUnit logic

MicroUnit doesn't call the single writeResult method at all.  
It is mean't to be called internally by your `writeResults` implementation and contain the logic for writing a single result.

### `writeResults(array $results)`

Writes multiple test results.

This method is there to provide flexibility if you want to do something more specific then just calling `writeResult` in a loop.

if not the most basic implementation would be just creating a loop that calls `writeResult` for every TestResult that get's passed.

#### When is it called by the MicroUnit logic

MicroUnit calls this method **each time** after running the tests of one test suite to output the results.

### `writeSummary(int $total, int $successes, int $failures)`

Writes a summary of all the test runs.

#### When is it called by the MicroUnit logic

MicroUnit calls this method **once** after all tests have been run.

### `writeSuite(string $suite)`

Writes the suite name.

#### When is it called by the MicroUnit logic

## MicroUnit calls this method **once every time before calling `writeResults`**.

**[⬆ Back to Top](#)** • **[📘 Home](index.md)**
