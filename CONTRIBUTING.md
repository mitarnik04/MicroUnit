# Contributing to MicroUnit

First off, thanks for taking the time to contribute! ❤️

All types of contributions are encouraged and valued. See the [Table of Contents](#table-of-contents) for different ways to help and details about how this project handles them. Please make sure to read the relevant section before making your contribution. It will make it a lot easier for us maintainers and smooth out the experience for all involved. The community looks forward to your contributions. 🎉

> And if you like the project, but just don't have time to contribute, that's fine. There are other easy ways to support the project and show your appreciation, which we would also be very happy about:
>
> - Star the project
> - Tweet about it
> - Refer this project in your project's readme
> - Mention the project at local meetups and tell your friends/colleagues

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [I Have a Question](#i-have-a-question)
- [I Want To Contribute](#i-want-to-contribute)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)
- [Your First Code Contribution](#your-first-code-contribution)
- [Improving The Documentation](#improving-the-documentation)
- [Styleguides](#styleguides)
- [Commit Messages](#commit-messages)
- [Join The Project Team](#join-the-project-team)

## Code of Conduct

This project and everyone participating in it is governed by the
[Code of Conduct](CODE_OF_CONDUCT.md).
By participating, you are expected to uphold this code. Please report unacceptable behavior
directly to mitar.mn5@gmail.com.

## I Have a Question

> If you want to ask a question, we assume that you have read the available [Documentation](https://mitarnik04.github.io/MicroUnit/).

Before you ask a question, it is best to search for existing [Issues](/issues) that might help you. In case you have found a suitable issue and still need clarification, you can write your question in this issue. It is also advisable to search the internet for answers first.

If you then still feel the need to ask a question and need clarification, we recommend the following:

- Open an [Issue](/issues/new).
- Provide as much context as you can about what you're running into.
- Provide project and platform versions (nodejs, npm, etc), depending on what seems relevant.

We will then take care of the issue as soon as possible.

## I Want To Contribute

> ### Legal Notice
>
> When contributing to this project, you must agree that you have authored 100% of the content, that you have the necessary rights to the content and that the content you contribute may be provided under the project license.

### Reporting Bugs

#### Before Submitting a Bug Report

A good bug report shouldn't leave others needing to chase you up for more information. Therefore, we ask you to investigate carefully, collect information and describe the issue in detail in your report. Please complete the following steps in advance to help us fix any potential bug as fast as possible.

- Make sure that you are using the latest version.
- Determine if your bug is really a bug and not an error on your side e.g. using incompatible environment components/versions (Make sure that you have read the [documentation](https://mitarnik04.github.io/MicroUnit/). If you are looking for support, you might want to check [this section](#i-have-a-question)).
- To see if other users have experienced (and potentially already solved) the same issue you are having, check if there is not already a bug report existing for your bug or error in the [bug tracker](issues?q=label%3Abug).
- Also make sure to search the internet (including Stack Overflow) to see if users outside of the GitHub community have discussed the issue.
- Collect information about the bug:
- Stack trace (Traceback)
- OS, Platform and Version (Windows, Linux, macOS, x86, ARM)
- Version of the interpreter, compiler, SDK, runtime environment, package manager, depending on what seems relevant.
- Possibly your input and the output
- Can you reliably reproduce the issue? And can you also reproduce it with older versions?

#### How Do I Submit a Good Bug Report?

> You must never report security related issues, vulnerabilities or bugs including sensitive information to the issue tracker, or elsewhere in public. Instead sensitive bugs must be sent by email to mitar.mn5@gmail.com.

We use GitHub issues to track bugs and errors. If you run into an issue with the project:

- Open an [Issue](/issues/new). (Since we can't be sure at this point whether it is a bug or not, we ask you not to talk about a bug yet and not to label the issue.)
- Explain the behavior you would expect and the actual behavior.
- Please provide as much context as possible and describe the _reproduction steps_ that someone else can follow to recreate the issue on their own. This usually includes your code. For good bug reports you should isolate the problem and create a reduced test case.
- Provide the information you collected in the previous section.

Once it's filed:

- The project team will label the issue accordingly.
- A team member will try to reproduce the issue with your provided steps. If there are no reproduction steps or no obvious way to reproduce the issue, the team will ask you for those steps and mark the issue as `needs-repro`. Bugs with the `needs-repro` tag will not be addressed until they are reproduced.
- If the team is able to reproduce the issue, it will be marked `needs-fix`, as well as possibly other tags (such as `critical`), and the issue will be left to be [implemented by someone](#your-first-code-contribution).

### Suggesting Enhancements

This section guides you through submitting an enhancement suggestion for MicroUnit, **including completely new features and minor improvements to existing functionality**. Following these guidelines will help maintainers and the community to understand your suggestion and find related suggestions.

#### Before Submitting an Enhancement

- Make sure that you are using the latest version.
- Read the [documentation](https://mitarnik04.github.io/MicroUnit/) carefully and find out if the functionality is already covered, maybe by an individual configuration.
- Check the sections talking about future features and make sure they don't already mention your functionality.
- Perform a [search](/issues) to see if the enhancement has already been suggested. If it has, add a comment to the existing issue instead of opening a new one.
- Find out whether your idea fits with the scope and aims of the project. It's up to you to make a strong case to convince the project's developers of the merits of this feature. Keep in mind that we want features that will be useful to the majority of our users and not just a small subset. If you're just targeting a minority of users the suggestion can be sent via email to mitar.mn5@gmail.com for further discussion.

#### How Do I Submit a Good Enhancement Suggestion?

Enhancement suggestions are tracked as [GitHub issues](/issues).

- Use a **clear and descriptive title** for the issue to identify the suggestion.
- Provide a **step-by-step description of the suggested enhancement** in as many details as possible.
- **Describe the current behavior** and **explain which behavior you expected to see instead** and why. At this point you can also tell which alternatives do not work for you.
- You may want to **include screenshots and animated GIFs** which help you demonstrate the steps or point out the part which the suggestion is related to. You can use [this tool](https://www.cockos.com/licecap/) to record GIFs on macOS and Windows, and [this tool](https://github.com/colinkeenan/silentcast) or [this tool](https://github.com/GNOME/byzanz) on Linux. If you want to use any other tool for that **feel free**
- **Explain why this enhancement would be useful** to most MicroUnit users. You may also want to point out the other projects that solved it better and which could serve as inspiration.

### Your First Code Contribution

#### General Guidelines

Every contribution **must** follow these core rules:

1. We strive to be **fast and lightweight** always do your best to contribute code that follows this principle. Also, make performance optimizations where possible.

2. We have a **zero dependency** philosophy so whatever you implement has to be coded from scratch &rarr; **NO** external dependencies

#### Getting started

Unsure where to start? Look for issues labeled [`good first issue`](https://github.com/yourrepo/issues?q=label%3A%22good+first+issue%22). These are selected to help new contributors get familiar with the codebase.

If you need help:

- Comment on the issue and ask for clarification
- Join discussions in existing threads
- Ask maintainers to assign the issue to you

Once ready:

1. Fork the repository
2. Create a new branch: `git checkout -b my-contribution`
3. Make your changes
4. Update the documentation if needed see [this section](#improving-and-upating-the-documentation) for details
5. Make sure your changes fit the described [Naming Conventions](#naming-conventions)
6. Submit a Pull Request with a clear explanation of your change
   - Ensure that all code in the pull request has been tested and functions as intended.
   - Provide as much context as possible to make it easier for reviewers to spot, understand and review your changes.
   - Make sure to add screenshot if the changes are visual.

We'll review your PR, suggest improvements if needed, and merge it when it's ready. 😊

### Improving and Upating The Documentation

Make sure that any changes you make to the code are also documented.

- **If you change existing behaviour** &rarr; update the necessary parts in the documentation.

- **If you add new behaviour** &rarr; create a new documentation section or even page for it (depending on the size and content of your addition)
  - When adding a new page or section make sure to update existing places (like tables of contents) where it might be useful to mention/link to your page/new section

#### If you are looking to update documentation without code changes

- The [troubleshooting.md](docs/troubleshooting.md) is a good place to start adding more documentation to MicroUnit

## Styleguides

All styleguides in regards to the codebase.

### PSR-4

To stay PSR-4 compliant each file should contain only one class. You need to create a new class &rarr; add a new file.

Refer to [Filenames](#filenames) for how to name your file.

### Naming Conventions

#### Folders

Should...

- Use `PascalCase` if they are **inside src** (e.g. `Assertion`, `Bootstrap`).
- use `snake_case`if they are **on root level** (e.g. `docs`, `src`).

#### Files

Should...

- Be PSR-4 compliant so make sure your filenames always match the name of the class inside the file (e.g. `Tester.php`, `Test.php`).

#### Namespaces

Should...

- use `PascalCase`.
- always start with `MicroUnit\`.
- mirror the directory structure (e.g. `MicroUnit\Core\`, `MicroUnit\Mock`).

#### Interfaces

Should...

- always be prefixed with I (e.g. `ITestWriter`, `IResult`).
- use standard `PascalCase` after the I (e.g. `ICreator`, `IWordGenerator`).
- describe the interfaces intent.

#### Classes

Should...

- use `PascalCase` (e.g. `TestCase`, `MicroMock`).
- describe the classes content.
- have the base class or interface name as a suffix if they extend/implement it. **In case of an interface** remove the leading I when using it as a suffix (e.g. `ITestWriter` &rarr; `StringTestWriter`).

#### Methods/Functions

Should...

- use `camelCase` (e.g. `getTester()`, `generate()`).
- use typehints for the return value and parameters wherever possible.
- only have **minimal** PHPDoc if...
  - able to provide more detailed typehinting with PHPDoc (e.g. `array` &rarr; can be typehinted as for example `array<string>` or `array<string, TestCase>` to give further information about it's content).
  - in need for a little extra information to make it's intent fully clear &rarr; should not happen that often since we aim to use descriptive names instead.
- describe what the method does.
- **Avoid** describing what is already clear from the method signature (e.g. **don't use** `generateFromString` &rarr; clear when looking at the parameters,  
  `formatReturnsString` &rarr; clear when looking at the return).
- use get in the name if they return a value (e.g `getName()`, `getTester()`).
  - **The expections to this rule** are when using a different prefix might give more information on what the method does (e.g. `buildConnectionString()`, `generateResponses()`).
- have names that sound like questions if returning a boolean value for more readability (e.g `isConnected()`, `hasKey()`).
- Be suffixed with the unit they return when returning values that can have different units with a fixed unit (e.g. `getDistanceInMiles(), getTimeInMs()`).

#### Constants

Should...

- use `SCREAMING_SNAKE_CASE` (e.g. `SPACE_UNIT`, `SRC_DIR`).
- have a descriptive name of what value they contain.
- have a typehint wherever possible.
- be suffixed with the unit of the value when containing a value that can have multiple units (e.g. `TIMEOUT_IN_MS`).

#### Properties

Should...

- use `camelCase` (e.g `logger`, `stopOnFailure`).
- have a descriptive name explaining it's content (e.g. `stopOnFailure` &rarr; together with the typehint you know that it contains information about whether or not something should stop on failure).
- have a typehint wherever possible.
- for PHPDoc same applies as for [Methods/Functions](#methodsfunctions).
- Have names that sound like questions if they are of type boolean, for more readability (e.g `isStopped`, `hasFailed`).
- be suffixed with the unit of the value when containing a value that can have multiple units (e.g. `TIMEOUT_IN_MS`).

#### Variables / Parameters

Should...

- use `camelCase` (e.g `token`, `mockBuilder`).
- have a descriptive name explaining it's content.
- have a typehint wherever possible.
- for PHPDoc same applies as for [Methods/Functions](#methodsfunctions).
- have names that sound like questions if they are of type boolean, for more readability (e.g `isValid`, `hasName`).
- when containing a value that can be described in different units be suffixed with the unit of the value (e.g. `timeInMs`, `distanceInMiles`).

### Commit Messages

- Keep your commit messages short but descriptive.
- Don't explain what is already clear from looking at the changes
  - Give insight on top of the changes so it is easier to get the full picture
- Keep it short: Nobody is going to read through long commit messages stick to **just a couple words !**

## Join The Project Team

Become a part of MicroUnit by making your first contribution. Whether it's fixing a bug, suggesting an enhancement, or improving the documentation. Every contribution helps the project grow and move closer to becoming a relevant and reliable part of the PHP ecosystem.

Don't forget to

- Star the Project on GitHub
- Share it on your Socials and with your friends and coworkers
- Follow us on X ([click here](https://x.com/MicroUnitPHP)) to always stay up to date with the latest news
