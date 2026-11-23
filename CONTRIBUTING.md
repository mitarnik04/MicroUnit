# Contributing to MicroUnit

First of all: thank you for even opening this file.

MicroUnit is a PHP testing framework with zero dependencies. Assertions, mocking, code coverage, parallel runs and a test explorer, all written in plain PHP. The goal is a modern testing tool that is a pleasure to use in every project where those things matter: tests that read like a description, runs fast enough that you never wait for them, and nothing extra to install.

That's a big goal for a small project, and right now it's mostly me working on it. So yes, I really do want your help. A typo fix counts. So does a bug report with a five-line reproduction, or a deep dive into the coverage instrumenter. All of it moves the project forward.

## Table of contents

- [Ways to help](#ways-to-help)
- [Get running in five minutes](#get-running-in-five-minutes)
- [Making a change](#making-a-change)
- [What makes a pull request easy to merge](#what-makes-a-pull-request-easy-to-merge)
- [AI-assisted contributions](#ai-assisted-contributions)
- [Code style](#code-style)
- [Writing docs and other text](#writing-docs-and-other-text)
- [Releasing](#releasing)
- [Reporting bugs](#reporting-bugs)
- [Suggesting features](#suggesting-features)
- [Questions](#questions)
- [Code of conduct](#code-of-conduct)

## Ways to help

You don't need to understand the whole codebase to make a real difference. Roughly from "ten minutes" to "a weekend":

- **Try MicroUnit in your project** and tell me where it hurt. Confusing errors, missing docs, an assertion you expected to exist. That feedback is gold.
- **Improve the docs.** The [troubleshooting page](docs/troubleshooting.md) only grows when people hit problems and write them down.
- **Pick up a [`good first issue`](https://github.com/mitarnik04/MicroUnit/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22).** These are scoped so you can finish them without knowing everything.
- **Write a test writer** for a format you need (TAP, Markdown summaries, Slack messages, ...). The interface is four methods, see `src/Output/ITestWriter.php`.
- **Add a framework integration.** Laravel, Symfony and CodeIgniter ship with 1.0. Yii, Laminas, Slim or CakePHP are open: implement `src/Integration/IFrameworkIntegration.php`.
- **Go deep.** The coverage instrumenter (`src/Coverage/`), the mock generator (`src/Mock/`) and the parallel runner (`src/Parallel/`) are the most interesting code in the repo. Bug reports and fixes there are especially welcome.

Not sure where to start? Open an issue and say what you're interested in. I'll point you somewhere useful.

## Get running in five minutes

You need **PHP 8.5** and git. That's it. MicroUnit has no Composer dependencies, so there's nothing to install.

```bash
git clone https://github.com/mitarnik04/MicroUnit.git
cd MicroUnit
php bin/microunit
```

That runs MicroUnit's own test suite (`tests/`), with MicroUnit. When it ends with a green summary, you're set up.

A few commands you'll use all the time:

```bash
php bin/microunit --filter=Mock         # only tests whose "Suite::test" id contains "Mock"
php bin/microunit tests/Unit/Mock       # only the tests in a directory or file
php bin/microunit --parallel            # use every CPU core
php bin/lint.php                        # syntax, structure and docs checks
php bin/canary.php                      # MicroUnit still tells passing tests from failing ones
```

`ARCHITECTURE.md` explains how a run works and where everything lives. Read it before touching the runner, coverage or the explorer, it'll save you an evening.

Want to try things by hand? Create a `playground/` folder with its own `microunit.config.php` and some test files, then run `php ../bin/microunit` from inside it. The folder is git-ignored, so you can't accidentally commit your experiments.

## Making a change

1. Fork the repository and create a branch: `git checkout -b fix-diff-for-nested-arrays`.
2. Write a test that shows the bug or describes the new behaviour. MicroUnit's tests live in `tests/Unit/<Namespace>/<Thing>Test.php`, one suite per file.
3. Make it pass.
4. Run `php bin/microunit`, `php bin/lint.php`, `php php-cs-fixer.phar fix` and `php phpstan.phar analyse` (see [Formatting](#formatting)). Everything must be green.
5. If users will notice the change, update the docs in `docs/` and add a line to `CHANGELOG.md`.
6. Open a pull request. Tell me what changed and, more importantly, why.

Small pull requests get reviewed fast. If you're planning something big, open an issue first so we can agree on the approach before you sink a weekend into it.

## What makes a pull request easy to merge

Four things matter more than anything else:

1. **Zero dependencies.** No Composer packages, not even dev-only ones. If you need a parser, a diff or an HTTP server, we write it. Yes, really. It's the whole point.
2. **Easy to write, easy to read.** A test written with MicroUnit should read top to bottom like a description of what the code does. New public APIs need names that say what happens, and no hidden magic. If you have to explain an API in a paragraph, it probably needs a better shape.
3. **Fast.** Startup time and per-test overhead matter. Don't do work at runtime that could be done once.
4. **Clean, maintainable code.** See [Code style](#code-style).

And please: tests for every behaviour change. A fix without a test tends to come back.

## AI-assisted contributions

Use whatever tools help you. A lot of MicroUnit 1.0 was built together with AI, I'm not going to pretend otherwise.

The one rule: **you understand what you submit, and you've run it.** I review AI-assisted pull requests the same way as any other, and "the AI wrote it" isn't an answer to a review question. If a coding agent works on the repo, it'll pick up `CLAUDE.md`, which holds the same rules as this file in compact form.

## Code style

### Principles

- **Separation of concerns.** One class, one reason to change. Parsing, deciding, executing and presenting live in different classes.
- **KISS and YAGNI.** The simplest design that solves today's problem. No options, factories or interfaces "for later", and no dead code.
- **DRY, with judgment.** Extract shared logic when it really is the same concept, not because two lines look alike.
- **Small, readable units.** A method does one thing and reads top to bottom. Prefer a better name over a comment. Comments explain *why*, not *what*.
- **Immutable and final by default.** `final readonly class` for value objects. Classes are `final` unless they're designed to be extended.
- **Type everything.** Parameters, return types, properties, constants (`const int`).
- **Errors that help.** An exception message names the problem and how to fix it.
- **Modern PHP.** MicroUnit targets PHP 8.5 and is tested on 8.6 as well. Use 8.5 features where they make the code clearer, not to show them off; nothing that needs 8.6 yet.

### Formatting

You don't have to think about formatting, tools do it for you:

- **`.editorconfig`** sets indentation, line endings and whitespace. Most editors pick it up automatically (VS Code needs the EditorConfig extension).
- **[PHP-CS-Fixer](https://cs.symfony.com/)** formats the code (PSR-12 plus a few rules of ours, all in `.php-cs-fixer.dist.php`). It's not a Composer dependency, we use the standalone PHAR:

  ```bash
  curl -sSL -o php-cs-fixer.phar https://cs.symfony.com/download/php-cs-fixer-v3.phar
  php php-cs-fixer.phar fix
  ```

  The PHAR is git-ignored. Run `fix` before you push.
- **`php bin/lint.php`** checks what a formatter can't: valid syntax, one class per file, file name equals class name, namespace matches the folder, interfaces start with `I`. It also checks that every CLI option, config method and assertion is documented, and that every link in the docs and the README leads somewhere.
- **[PHPStan](https://phpstan.org/)** checks types, also as a standalone PHAR (git-ignored, never a dependency), with the settings in `phpstan.neon.dist`:

  ```bash
  curl -sSL -o phpstan.phar https://github.com/phpstan/phpstan/releases/download/2.3.7/phpstan.phar
  php phpstan.phar analyse
  ```

CI runs all of them on every pull request, together with the test suite on three operating systems and two PHP versions, the canary (`bin/canary.php`), the coverage floor and the integrity check. [Ensuring high quality with AI](https://mitarnik04.github.io/MicroUnit/quality-with-ai/) explains each one. Running them locally just saves you a round trip.

### File structure

One class per file, file name equals class name (PSR-4). Inside a file:

1. `namespace`
2. `use` statements
3. the class, ordered as: constants, public properties, private properties, constructor, magic methods, public methods, private methods

### Naming

| What | Convention | Examples |
| --- | --- | --- |
| Folders in `src/` | `PascalCase` | `Assertion`, `Coverage` |
| Folders in the repository root | `snake_case` | `docs`, `assets` |
| Namespaces | `MicroUnit\` + the folder path | `MicroUnit\Core` |
| Classes | `PascalCase`, implementations end with the interface name minus the `I` | `TestCase`, `StringTestWriter` |
| Interfaces | prefixed with `I` | `ITestWriter`, `IFrameworkIntegration` |
| Methods | `camelCase`, describe what they do | `getTester()`, `buildReport()` |
| Booleans (methods, properties, variables) | read like questions | `isSuccess`, `hasKey()` |
| Constants | `SCREAMING_SNAKE_CASE`, typed | `const int TEST_FAILED_CODE` |
| Values with a unit | the unit as a suffix | `timeInMs`, `TIMEOUT_IN_SECONDS` |

Don't repeat what the signature already says: `generate(string $input)`, not `generateFromString(string $input)`.

### Commit messages

Short and to the point. Say what the diff can't tell you on its own.

## Writing docs and other text

The docs, the README and everything else MicroUnit says about itself follow the voice and the rules in [PRODUCT.md](PRODUCT.md#brand-commitments). It also says who MicroUnit is for and how it is positioned, so it is worth a read before you write anything users will see.

## Releasing

A release needs the whole CI pipeline green, and one more gate that is too slow for every push: the test suites of the six real projects in `benchmarks/`. Run the harness as [`benchmarks/README.md`](benchmarks/README.md#reproducing) describes. Before it measures anything, it migrates each project's tests and checks that every test in the set passes under MicroUnit and that MicroUnit runs as many tests as PHPUnit. A release needs every scenario of every project to be valid. Commit the results folder with the release, so each release has its record.

## Reporting bugs

Found something broken? Please [open an issue](https://github.com/mitarnik04/MicroUnit/issues/new?template=bug_report.md). The fastest way to get it fixed:

- the smallest test file and config that reproduce the problem
- what you expected and what happened instead (paste the output)
- your PHP version, OS and MicroUnit version (`vendor/bin/microunit --version`)
- the run log from `.microunit/logs/` if MicroUnit crashed

**Security issues go by email, not into issues.** See [SECURITY.md](SECURITY.md).

## Suggesting features

[Open an issue](https://github.com/mitarnik04/MicroUnit/issues/new?template=feature_request.md) and describe the problem you're trying to solve before the solution you have in mind. "My tests need a database and I want it set up once" is easier to design for than "add a `beforeAll` annotation". If another tool solves it well, link it, that helps.

## Questions

Check the [documentation](https://mitarnik04.github.io/MicroUnit/) first. If you're still stuck, [open an issue](https://github.com/mitarnik04/MicroUnit/issues/new) with as much context as you can. There are no stupid questions here, and every question tells me where the docs need work.

## Code of conduct

Everyone taking part in this project follows the [Code of Conduct](CODE_OF_CONDUCT.md). Please report unacceptable behaviour to mitar.mn5@gmail.com.

---

Thanks again. Whatever you end up contributing, it helps.

Mitar
