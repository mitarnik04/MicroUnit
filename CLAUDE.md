# MicroUnit

Dependency-free, pure-PHP testing framework (assertions, mocking, coverage, parallel runs, test
explorer, framework integrations). It tests itself: `tests/` is run by MicroUnit. Who it is for, how
it is positioned, its voice and brand colours: `PRODUCT.md`. Read it before writing docs, copy or
designs.

## Rules

- **Easy to write, easy to scan.** Tests (human- or AI-written) must read top to bottom like a
  description of behaviour. Judge every public API by this first: plain names, PHPUnit argument
  order (expected, actual), named args / fluent `with…()`, no hidden magic.
- **Zero dependencies.** No Composer packages, not even `require-dev`. Write it yourself.
- **PHP 8.5+.** Use modern features where they make code clearer or faster.
- **Code quality** per `CONTRIBUTING.md#code-style`: separation of concerns, KISS, YAGNI,
  `final readonly` by default, typed, helpful errors, no dead code.
- **Fast.** Startup and per-test overhead matter.
- **Configurable in PHP.** Options live on `MicroUnitConfigBuilder`; CLI flags only override per run.
- **Done means:** tests added/updated, suite, canary, lint and PHPStan pass, `docs/` (+ `README.md`,
  `CHANGELOG.md` for public changes) updated.
Read `ARCHITECTURE.md` before changing the run flow or a subsystem. `docs/` is the user-facing contract.

## Commands

| Task | Command |
| --- | --- |
| Run the self-test suite | `php bin/microunit` |
| Subset | `php bin/microunit --filter=Mock` or `php bin/microunit tests/Unit/Mock` |
| Parallel | `php bin/microunit --parallel=4` |
| Coverage | `php bin/microunit --coverage` |
| Lint (incl. docs checks) | `php bin/lint.php` |
| Canary | `php bin/canary.php` |
| Static analysis | `php phpstan.phar analyse` (PHAR, see `CONTRIBUTING.md#formatting`) |
| Help for every command | `php bin/microunit help [run\|init\|migrate\|integrity\|explorer]` |
| Benchmarks (quiet machine only) | see `benchmarks/README.md` |
| Rebuild brand PNGs | `php assets/brand/build.php` (needs Chrome) |

In Claude Code the output is compact JSON (`CLAUDECODE=1`); pass `--no-agent` for the
human-readable output. The Test Explorer is opt-in (`--explorer`), so no browser tab opens. Exit
codes: `0` pass, `1` failures, coverage below minimum or a strict check, `2` usage/config error.

## Writing MicroUnit's own tests

One suite per file in `tests/Unit/<Namespace>/<Thing>Test.php`, suite name = unit under test, test
names in `snake_case` describing behaviour. Assert failures with
`Assert::throws(fn () => …, TestFailedException::class)`. Helper classes go in `tests/Fixtures/`
(namespace `MicroUnit\Tests\Fixtures`). Integration tests spawn the CLI via `tests/Support/Cli.php`
against `tests/Fixtures/projects/*` (those use the `*-tests.php` pattern so the outer run ignores them).

## Gotchas

- Use `TestSetup::getTester('Suite')`, never `new Tester()`.
- With `opcache.enable_cli=1`, OPcache can bypass coverage instrumentation; MicroUnit warns.
- New CLI options go into `CliOptions` and `HelpText` (help is data, rendered per command).
- `docs/assets/css/brand.css` is a copy of `resources/brand.css`; lint fails when they differ.
- `playground/` is a git-ignored scratch area with its own config; never commit it.
