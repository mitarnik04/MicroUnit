# Architecture

How MicroUnit is put together. Read this before changing the run flow or a subsystem.

## Layout

```
bin/microunit            CLI entry point (bin/run-tests.php is a BC shim)
src/
  Assertion/             Assert (static, PHPUnit argument order) built from the traits in Assert/*,
                         fluent AssertSingle/Numeric/Array/String/File, AssertMock(+Method), AssertResponse,
                         AssertionFailure, ForeignAssertionFailure (PHPUnit interop)
  Bootstrap/             Own autoloader, lazy project autoloader, config discovery, run log, runtime dir
  Cli/                   CliOptions (parsing), Application (orchestrates one run), HelpText (data)
  Config/                MicroUnitConfig (immutable) + MicroUnitConfigBuilder, CoverageConfig, TestGroup
  Core/                  Tester (per-suite definition API), TestRegistry, TestPlan, TestFilter,
                         TestRunner (one test), SuiteRunner (suites + once-hooks), FixtureResolver,
                         TestContext (current test, assertion count), TestResult, RunSummary
  Coverage/              Pure-PHP coverage: Instrumenter, IncludeInterceptor, Probe, CoverageData, Report/*
  Exceptions/            TestFailedException, TestSkippedException
  Explorer/              ExplorerReport (JSON state), ExplorerServer (HTTP on 127.0.0.1), ExplorerLauncher,
                         StaticReport (read-only HTML for HtmlTestWriter)
  Helpers/               Diff (LCS), ValueExporter, Filesystem, Glob, PhpProcess
  Init/                  `microunit init` (framework detection, config + example test, agent skill)
  Integration/           IFrameworkIntegration, TestCaseHost + Laravel/Symfony/CodeIgniter integrations
  Integrity/             `microunit integrity` and withIntegrityCheck(): TestSource (static analysis of
                         test files), IntegrityCheck (compares with `git show REV:file`)
  Migrate/               `microunit migrate`: ClassParser → TestClass (merges parents/traits) →
                         SuiteTranslator (+ BodyRewriter, MockTranslator, AssertionMap) → files + report
  Mock/                  MockBuilder → MockDefinition → MicroMock; MockClassGenerator; MockRegistry; CallLog
  Output/                ITestWriter + writers (console, agent JSON, JUnit, HTML, log, GitHub);
                         Output/Format/* shared formatters and TextStyle (ANSI)
  Parallel/              ParallelRunner (proc_open workers), WorkerRunner
  Setup/TestSetup.php    Public entry point for test files
  Toolkit/               Integration-test helpers: Database, HttpServer, Http, TemporaryDirectory, ValueBundle
resources/               explorer.html, report/coverage CSS, fonts, agent/SKILL.md (installed by init --skill)
tests/                   MicroUnit's own suite (Unit/, Integration/, Fixtures/, Support/)
benchmarks/              Reproducible benchmark harness and dated results (not shipped with the package)
assets/brand/            Logo, banners, screenshots + their sources (not shipped with the package)
docs/                    GitHub Pages documentation (user-facing contract)
```

## How a run works

1. `bin/microunit` registers MicroUnit's own autoloader and hands argv to `Cli\Application`, which
   dispatches the commands (`init`, `migrate`, `integrity`, `explorer`, `help`) or starts a run.
2. The config is loaded (searched from the working directory upwards). Paths in it are relative to
   the config file; absolute paths work too. Runtime files go to `.microunit/` (configurable).
3. If coverage is on, `Coverage\IncludeInterceptor` replaces the `file://` stream wrapper *before*
   the project's Composer autoloader is loaded, so every included source file under the coverage
   paths is instrumented on the fly. Line numbers, `__FILE__` and `__DIR__` stay unchanged.
4. The framework integration boots (if configured), then the bootstrap file and all test files are
   required, each in its own scope. Test files only *register* tests; nothing runs at include time.
5. A `TestPlan` (registered tests minus `--filter`/`--failed`/explorer selection) is executed by
   `SuiteRunner`, or by `ParallelRunner`, which deals whole suites over worker processes (largest
   first) and merges results (and coverage) back in definition order.
6. Writers receive results per suite, then the summary. Coverage reports are written, the strict
   checks evaluated (coverage minimum, `failOnRiskyTests`, integrity findings), the explorer state is
   updated (and the explorer opened when interactive), and the exit code is returned. In agent
   sessions the checks run before the summary, so the single JSON object knows every reason the
   run fails.

## Subsystem notes

- **Assertions** count themselves in `TestContext` (tests without any are "risky") and fail through
  `Assert::failWith()`, which supports soft failures for `Assert::multiple()` (`AssertMock` and
  `AssertResponse` use it too). The passing path stays cheap: `if (!self::passes($condition))`
  guards the failure message, exported values and diffs, which are only built on failure.
- **Mocks** are eval'd subclasses, cached per "shape" (target + generated methods). Generated
  methods call `MockRegistry`, which maps each instance (WeakMap) to its `MicroMock`; that is how
  per-instance and aggregated call logs coexist without polluting generated classes.
- **Coverage** counts statements: a line is executable when an executable statement (or an arrow
  function body) starts on it. Probes are inserted on the same line, so line numbers never shift.
  Instrumented files are cached per path + size + mtime in `.microunit/cache/coverage`.
- **Explorer**: the CLI writes `.microunit/explorer/report.json`; a tiny PHP HTTP server serves the
  single-file UI and runs tests on request (token-protected, 127.0.0.1 only; a selection is passed
  to the run as `--ids-file`). `HtmlTestWriter` renders the same data as a static, read-only page.
- **Parallel** workers are the same CLI with `--worker=i/n`; they write serialized results to a temp
  file. Without coverage they get a persistent OPcache file cache for faster startups.
- **Framework integrations** create one instance of the framework's PHPUnit test case per test
  (`TestCaseHost`) and run its `setUp()`/`tearDown()` around the MicroUnit test; proxies
  (`Laravel::`, `Symfony::`, `CodeIgniter::`) forward calls to it. PHPUnit failures become MicroUnit
  failures (`ForeignAssertionFailure`).
- **Agent output** (`AgentTestWriter`) replaces the console writers when `CLAUDECODE=1` or
  `MICROUNIT_AGENT=1`; the integrity check is opt-in (`withIntegrityCheck()`) and only reads git.
- **Migrate** works on tokens only (no PHP parser dependency): single-character tokens are matched
  by id (`Code::char`), never by text, so string fragments can't break bracket matching.
