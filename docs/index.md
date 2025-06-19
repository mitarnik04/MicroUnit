# MicroUnit Documentation

Welcome to the MicroUnit documentation!

MicroUnit is a lightweight, expressive, and flexible unit testing framework for PHP.  
It is designed to help you write clear, maintainable, and robust tests for your PHP projects with minimal setup and maximum power.

---

## Documentation Overview

- **[Assertions](assertions.md)**  
  Learn about all assertion methods, including static and chainable usage.

- **[Configuration](configuration.md)**  
  Discover all configuration options and how to customize your test environment.

- **[Mocking](mocking.md)**  
  Explore the mocking engine for creating and verifying test doubles.

- **[Output](output.md)**  
  See how to configure and extend test output writers.

- **[Usage](usage.md)**  
  Get started with defining, grouping, and running tests.

- **[Logging](logging.md)**  
  Learn more about the logging system in microunit, how it works, where your logs are, etc

- **[Troubleshooting](troubleshooting.md)**  
  Are you having trouble with running your tests? Check out this section where the usual pitfalls are documented and how to overcome them

---

## Quick Start

1. **Install MicroUnit**  
   Add MicroUnit to your project using Composer:

   ```sh
   composer require your-vendor/microunit --dev
   ```

2. **Create your microunit.config.php**
   Create `microunit.config.php` in your current working directory or any parent directories:

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

3. **Write your first test**
   Create a test file in your specified test directory (or any child directory) and start testing.

   ```php
   use MicroUnit\Assertion\Assert;
   use MicroUnit\Setup\TestSetup;

   $tester = TestSetup::getTester("YOUR_TEST_SUITE_NAME");
   $tester->define('my_first_test', function(){
        Assert::equals(2, 1 + 1);
   })
   ```

4. **Run Your Tests**  
    Use your preferred test runner or CLI integration.

   ```bash
   vendor/bin/microunit
   ```

---

## Why MicroUnit?

- **Simple, expressive assertions**
- **Powerful mocking and call tracking**
- **Flexible configuration**
- **Customizable output**
- **Fast and easy to integrate**

---

## More Resources

- [GitHub Repository](https://github.com/mitarnik04/MicroUnit)
- [X (aka Twitter)](https://x.com/MicroUnitPHP)

---

**[⬆ Back to Top](#microunit-documentation)**
