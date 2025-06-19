---
layout: default
---

**[← Back to Home](index.md)**

# Usage

All about how to create your test file(s), define individual or grouped tests, etc.

---

## Create your first test file

See [Configuration: withTestDir](configuration.md#withtestdirstring-dir) to define your testing directory.

See [Configuration: addTestFilePattern](configuration.md#addtestfilepatternstring-pattern) to define your test file pattern.

Let's say we defined our test directory to be `/tests` and our test file pattern to be `-*tests.php`.

With this set up you can create as many test files as you want in your defined test directory or any child directories.

So for this example let's say we create `/tests/example-tests.php`.

## Obtaining a Tester instance

Before we can start writing tests we need to get an instance of `MicroUnit\Core\Tester.php`.

**DO NOT** just call `$tester = new Tester();` this will bypass a crucial part of the testing logic not allowing you to run your tests as expected.

Instead we use `MicroUnit\Setup\TestSetup` and call it's static method `getTester(string $suite)` to get a tester instance.

It is always one tester per suite. If you have all the tests for a suite in one file or in multiple files doesn't matter. As long as you use the correct suite name `getTester(string $suite)` will always give you the apropriate tester.

> **Note:** It is recommended to call `getTester` **only once** at the beginning of a test file to obtain a tester instance and then use that tester all across the file.

```php
<?php
$tester = TestSetup::getTester("MySuite");
```

Now we are all set to start writing our first tests.

## Defining Tests

After obtaining a $tester instance we can start to write tests.

The first way we can do this is by calling `$tester->define(string $testName, callable $test, ...$args)`.  
The last argument is only relevant if the anonymous function has parameters. Then you pass the values of these parameters as `...$args`

```php
$tester->define('should_add_numbers', function () {
    Assert::equals(2, 1 + 1);
});
```

---

## Grouped Tests

You can write data-driven (parameterized) tests by using the `$tester->defineGroup(string $baseName, callable $test, array $cases)` method.

Each `TestCase` (`MicroUnit\Core\TestCase`) will produce one individual test and expects two constructor arguments `string $testNameSuffix` and `mixed $params`.

The `testNameSuffix` will be attached to the basename to create a unique test name.

The `params` are used to pass the function parameters for each case. If you have a single value you can pass it directly if you have a function that expects multiple values pass them as an array (as shown below).

```php
$cases = [
    new TestCase('zero', [0, 0, 0]),
    new TestCase('positive', [1, 2, 3]),
];
$tester->defineGroup('addition', function ($a, $b, $expected) {
    Assert::equals($expected, $a + $b);
}, $cases);

/*
* Will create two tests with the following names and arguments:
*
* - addition_zero:
*       $a = 0;
*       $b = 0;
*       $expected = 0;
*
* - addition_positive
*       $a = 1;
*       $b = 2;
*       $expected = 3;
*/
```

---

## Lifecycle Hooks

Run code before and after each test.

### setUp

Called **once** before each test

```php
$tester->setUp(function () {
    // Is executed once before each test
    // Return any value you want to share with your tests
});
```

#### Using the setUp metods return value

Whatever you return from setUp **must** be the first argument of your test functions.

> **Note:** For the test functions that have no arguments at all you **don't** need to specify your setUp's return value as the first argument.  
> This is only true for functions that have **subsequent arguments** as not passing your setUps return value as the first argument will result in an `ArgumentCountError`.
>
> If you don't use the setUp function your test functions can have any arguments you want.

Example:

```php
$tester->setUp(function () {
    // This runs before each test
    // Return any value you want to share with your test
    return new DatabaseConnection();
});

// Each test must accept the setup result as its first argument!
$tester->define('fetch user', function ($db) {
    $user = $db->fetchUser(1);
    Assert::notNull($user);
});
```

##### What about defineGroup ?

When using defineGroup the setUp return value still has to be the first argument of your function followed by the arguments you pass in your TestCases.

```php
$tester->defineGroup(
    'addition',
    function ($setUpResult, $a, $b, $expected) {
        // $setUpResult comes from setUp() !!
        Assert::equals($expected, $a + $b);
    },
    [
        new TestCase('zero', [0, 0, 0]),
        new TestCase('positive', [1, 2, 3]),
    ]
);
```

### tearDown

Called **once** after each test

```php
$tester->tearDown(function ($result, $setUpResult) {
    // Is executed once after each test
    // $result will contain an Instance of TestResult.
    // $setUpResult will contain whatever setUp() returned
    // with all the modifications made to it inside the test.
});
```

### Full Example

A simple example to illustrate the use of lifecycle hooks.

```php
// Let's assume we have a simple DB.
$database = new Database();

$tester->setUp(function () use ($database) {
    // Establish a DB connection before each test
    return $database->connect(); // returns DatabaseConnection
});

$tester->define('fetch user', function (DatabaseConnection $db) {
    // Use the connection ($db) to fetch a user
    $user = $db->fetchUser(1);
    Assert::notNull($user);
});

$tester->tearDown(function ($testResult, DatabaseConnection $db) {
    // Close the connection after each test
    // This ensures each test starts with a fresh connection.
    $db->close();
});
```

---

## Running Tests

MicroUnit can be run from the terminal. The command varies slightly depending on your operating system.

### Linux / MacOS

MicroUnit should be installed via Composer so you can run:

```bash
vendor/bin/microunit
```

Or if you've made it globally executable:

```bash
./vendor/bin/microunit
```

### Windows

```cmd
vendor\bin\microunit.bat
```

Or from PowerShell:

```powershell
vendor/bin/microunit
```

> **Note:** On Windows, use backslashes \ in CMD, but forward slashes / work fine in PowerShell and Git Bash.

---

**[⬆ Back to Top](#)** • **[📘 Home](/index.md)**
