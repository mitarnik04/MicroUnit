---
layout: default
---

**[← Back to Home](/)**

# Troubleshooting

A list of common Errors and problems you might encounter when trying to set up / work with MicroUnit and how to solve them.

## Table of Contents

- [Configuration Issues](#configuration-issues)
  - [Your configuration settings are not being applied](#your-configuration-settings-are-not-being-applied)
  - [Fatal error: Uncaught TypeError...](#fatal-error-uncaught-typeerror)
  - [PHP Fatal error: Uncaught UnexpectedValueException...](#php-fatal-error-uncaught-unexpectedvalueexception)
- [Test File Issues](#test-file-issues)
  - [PHP Fatal error: Uncaught ArgumentCountError...](#php-fatal-error-uncaught-argumentcounterror-too-few-arguments-to-function)
  - [Unrecognized types and classes](#types-and-classes-are-not-recognized)
- [More yet to be added](#more-yet-to-be-added)

## Configuration issues

### Your configuration settings are not being applied

MicroUnit is probably not able to find your configuration.

Go to your run logs and look for a similar notice like below:

```log
 No config file found. Using default config in C:\PhpProjects\portfolio\vendor\microunit\microunit\src\Bootstrap\ConfigInitializer.php on line 36
```

#### Wrong name

Your config file has to be named `microunit.config.php` if it has a different name it will not be found.

#### Inside wrong directory

MicroUnit searches for your config file starting from the current working directory and working it's way up to the subsequent parent directories.  
So if you have stored it in a sub-directory that isn't on that path MicroUnit will not be able to find it.

Example:

```
/my-project
├── tools/
│   └── microunit.config.php  # NOT in search path if your cwd is /tests
├── tests/
│   └── UserTest.php
└── vendor/
```

**Fix:**

Move your config file into the search path

```
/my-project
├── microunit.config.php      # Found if cwd is /tests
├── tests/
│   └── UserTest.php
└── vendor/
```

### Fatal error: Uncaught TypeError: ...\ConfigProvider::set(): Argument #1 ($config) must be of type ...\MicroUnitConfig ...

An error like this in your logs means that your `microunit.config.php` file doesn't return an instance of `MicroUnit\Config\MicroUnitConfig`.

Example:

```php
<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

//Notice the return statement missing
MicroUnitConfigBuilder::create()
    ->withTestDir('/tests')
    ->addTestFilePattern('*-tests.php')
    ->build();
```

This problem may also occur if you forget to call `build()` in the end of your configuration or return something other than `MicroUnit\Config\MicroUnitConfig`.

**Fix:**  
Make sure your config file **always** returns an instance of `MicroUnit\Config\MicroUnitConfig`.

```php
<?php

use MicroUnit\Config\MicroUnitConfigBuilder;

return MicroUnitConfigBuilder::create()
    ->withTestDir('/tests')
    ->addTestFilePattern('*-tests.php')
    ->build();
```

### PHP Fatal error: Uncaught UnexpectedValueException: RecursiveDirectoryIterator::\_\_construct ...

If you encounter this error you have probably done one of two things (or both).

**You are either:**

using `withBootsrapFile` and constructing your path with `__DIR__`

**Or:**

using `withTestDir` and constructing your path with `__DIR__`

**Fix:**

If you are using the method `withBootsrapFile` or `withTestDir` the methods expects you to pass a path relative to the location of `microunit.config.php` **NOT** a path constructed with `__DIR__`. Passing such a path will result in an error being thrown.

## Test File Issues

### PHP Fatal error: Uncaught ArgumentCountError: Too few arguments to function {closure:some/path/to/closure}(), ... passed in ...

If you see an error that looks something like this then one of the things below might be causing it.

#### Case 1

You are using `define` and have a function with parameters but you are not passsing all of them as `...$args`.

Example:

```php
// function is expecting two arguments $a and $b
$tester->defineGroup('SomeTest', function ($a, $b) {
    Assert::isTrue(true);
}, 'first'); //However only one is passed
```

**Fix:**

Always make sure you are passing the right number of arguments to your functions

```php

// function is expecting two arguments $a and $b
$tester->defineGroup('SomeTest', function ($a, $b) {
    Assert::isTrue(true);
}, 'first', 'second'); // Therefore two arguments are passed
```

#### Case 2

You are using `defineGroup` but your function is expecting more arguments than you are passing.

Example:

```php
// function is expecting two arguments $a and $b
$tester->defineGroup('SomeTest', function ($a, $b) {
    Assert::isTrue(true);
}, [
    //However in the testcase only one argument is passed
    new TestCase('case1', 'somevalue'),
    new TestCase('case2', 'somevalue2'),
]);
```

**Fix:**

Always make sure you are passing the right number of arguments to your functions

```php

// function is expecting two arguments $a and $b
$tester->defineGroup('SomeTest', function ($a, $b) {
    Assert::isTrue(true);
}, [
    //Now we pass two arguments
    new TestCase('case1', ['first', 'second']),
    new TestCase('case2', ['first2', 'second2']),
]);
```

#### Case 3

You are using `setUp()` but your function does not have it's return value as the first argument

Example:

```php
//A setUp value is returned
$tester->setUp(function () {
    return 'someValue';
});

//However it is not added to the function parameters
$tester->defineGroup('SomeTest', function ($a) {
    Assert::isTrue(true);
}, [
    new TestCase('Case1', 'val1'),
    new TestCase('Case2', 'val2'),
]);

```

**Fix:**  
If you are using `setUp()` always make sure to inlcude it's return value as the first parameter of your functions.

The parameter can have any name (and you can even type hint it).

```php
//A setUp value is returned
$tester->setUp(function () {
    return 'someValue';
});

//And we pass it as the first parameter
$tester->defineGroup('SomeTest', function ($startUpResult, $a) {
    Assert::isTrue(true);
}, [
    new TestCase('Case1', 'val1'),
    new TestCase('Case2', 'val2'),
]);

```

### Types and Classes are not recognized

You try using `TestSetup` to get your tester instance or calling `Assert::...`, `AssertSingle::...`, `AssertArray::...`, `AssertNumeric::...`, etc. but the IDE and logs keep telling you that these types are unknown.

**Fix:**  
Always make sure to import the classes you use.

```php
use MicroUnit\Assertion\Assert;
use MicroUnit\Assertion\AssertArray;
use MicroUnit\Core\TestCase;
use MicroUnit\Setup\TestSetup;

// The rest of your code ...
```

## More yet to be added

Because I am currently one person single handedly maintaining and working on this project my time is limited.

This page will be filled with more content as MicroUnit is getting developed but currently my focus in on bringing new features and driving MicroUnit towards it's first stable relase.

But every time I have some space I will be refining this documentation further and therefore expanding this troubleshooting page as well.

---

**[⬆ Back to Top](#table-of-contents)** • **[📘 Home](/)**
