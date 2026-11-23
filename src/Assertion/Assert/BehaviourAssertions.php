<?php

namespace MicroUnit\Assertion\Assert;

use MicroUnit\Assertion\AssertionFailure;
use MicroUnit\Core\TestContext;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Exceptions\TestSkippedException;
use MicroUnit\Helpers\Diff;
use MicroUnit\Helpers\ValueExporter;

/** What code does when it runs: exceptions, errors, output, eventual consistency; plus control flow. Part of Assert. */
trait BehaviourAssertions
{
    private const array ERROR_LEVEL_NAMES = [
        E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE', E_DEPRECATED => 'E_DEPRECATED',
        E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    ];

    /**
     * Asserts that $callable throws. Returns the caught throwable for further assertions.
     *
     * @param callable(): mixed $callable
     * @param class-string<\Throwable>|null $expectedType
     * @param string|null $expectedMessage substring the exception message must contain
     */
    public static function throws(callable $callable, ?string $expectedType = null, ?string $expectedMessage = null, string $message = ''): \Throwable
    {
        $expectedType = $expectedType === null ? null : ltrim($expectedType, '\\');
        try {
            $callable();
        } catch (\Throwable $throwable) {
            $isExpectedType = $expectedType === null || $throwable instanceof $expectedType;
            $hasExpectedMessage = $expectedMessage === null || str_contains($throwable->getMessage(), $expectedMessage);
            if (self::passes($isExpectedType && $hasExpectedMessage)) {
                return $throwable;
            }

            if (!$isExpectedType) {
                self::reject(
                    "Failed asserting that the thrown exception is of type $expectedType.",
                    $expectedType,
                    $throwable::class . ': ' . $throwable->getMessage(),
                    customMessage: $message,
                );
            }
            if (!$hasExpectedMessage) {
                self::reject(
                    'Failed asserting that the exception message contains the expected text.',
                    $expectedMessage,
                    $throwable->getMessage(),
                    customMessage: $message,
                );
            }

            return $throwable;
        }

        throw new TestFailedException(new AssertionFailure(
            'Failed asserting that ' . ($expectedType ?? 'an exception') . ' is thrown.',
            $expectedType ?? \Throwable::class,
            'nothing was thrown',
            customMessage: $message,
        ));
    }

    /**
     * Runs $callable and returns its result; an exception fails the test with a clear message
     * instead of surfacing as an error.
     *
     * @template T
     * @param callable(): T $callable
     * @return T
     */
    public static function doesNotThrow(callable $callable, string $message = ''): mixed
    {
        TestContext::countAssertions();
        try {
            return $callable();
        } catch (TestFailedException|TestSkippedException $assertionOrSkip) {
            throw $assertionOrSkip;
        } catch (\Throwable $throwable) {
            throw new TestFailedException(new AssertionFailure(
                'Failed asserting that no exception is thrown.',
                'nothing thrown',
                $throwable::class . ': ' . $throwable->getMessage() . ' at ' . $throwable->getFile() . ':' . $throwable->getLine(),
                customMessage: $message,
            ), $throwable);
        }
    }

    /**
     * Asserts that $callable triggers a PHP error of $level (warnings, notices, deprecations) and
     * returns its message. The error is captured, so it neither prints nor reaches other handlers.
     *
     * Assert::triggersError(fn () => $legacy->run(), E_USER_DEPRECATED, 'use runNew() instead');
     *
     * @param callable(): mixed $callable
     */
    public static function triggersError(callable $callable, int $level = E_ALL, ?string $expectedMessage = null, string $message = ''): string
    {
        $triggered = [];
        set_error_handler(static function (int $errorLevel, string $errorMessage) use (&$triggered): bool {
            $triggered[] = [$errorLevel, $errorMessage];

            return true;
        }, $level);
        try {
            $callable();
        } finally {
            restore_error_handler();
        }

        foreach ($triggered as [, $errorMessage]) {
            if ($expectedMessage === null || str_contains($errorMessage, $expectedMessage)) {
                TestContext::countAssertions();

                return $errorMessage;
            }
        }

        $levelName = self::ERROR_LEVEL_NAMES[$level] ?? ($level === E_ALL ? 'error' : "error (level $level)");
        throw new TestFailedException(new AssertionFailure(
            "Failed asserting that $levelName is triggered" . ($expectedMessage === null ? '.' : ' with the expected message.'),
            $expectedMessage ?? $levelName,
            $triggered === [] ? 'nothing was triggered' : array_map(static fn(array $error): string => (self::ERROR_LEVEL_NAMES[$error[0]] ?? $error[0]) . ': ' . $error[1], $triggered),
            customMessage: $message,
        ));
    }

    /**
     * Asserts that $callable prints exactly $expected (echo, print, printf, ...).
     *
     * @param callable(): mixed $callable
     */
    public static function prints(callable $callable, string $expected, string $message = ''): void
    {
        ob_start();
        try {
            $callable();
        } finally {
            $output = (string) ob_get_clean();
        }

        if (self::passes($output === $expected)) {
            return;
        }

        self::reject(
            'Failed asserting that the printed output equals the expected text.',
            $expected,
            $output,
            customMessage: $message,
            diff: Diff::fromLines(explode("\n", $expected), explode("\n", $output)),
        );
    }

    /**
     * Retries $check until it passes (no failed assertion, no exception, not false) or the timeout
     * runs out, then reports the last failure. Returns what $check returned. For queues, background
     * workers, servers and anything else that becomes true a moment later.
     *
     * $mail = Assert::eventually(fn () => $mailbox->latest() ?? false, timeoutInSeconds: 3);
     *
     * @template T
     * @param callable(): T $check
     * @return T|null null if the check never passed inside Assert::multiple()
     */
    public static function eventually(callable $check, float $timeoutInSeconds = 5.0, float $intervalInSeconds = 0.1, string $message = ''): mixed
    {
        $deadline = microtime(true) + $timeoutInSeconds;
        $attempts = 0;
        $isLastAttempt = false;
        while (true) {
            $attempts++;
            try {
                [$failures, $returned] = self::collectFailures($check);
                if ($failures === [] && $returned !== false) {
                    TestContext::countAssertions();

                    return $returned;
                }
                $lastFailure = $failures === [] ? 'The check returned false.' : self::describeFailures($failures, '');
            } catch (TestSkippedException $skip) {
                throw $skip;
            } catch (\Throwable $throwable) {
                $lastFailure = $throwable::class . ': ' . $throwable->getMessage();
            }
            $remainingSeconds = $deadline - microtime(true);
            if ($isLastAttempt || $remainingSeconds <= 0) {
                break;
            }
            // Sleeping up to the deadline makes the next attempt the last one, even when a coarse
            // clock (Windows) still shows a sliver of time left after waking up.
            $isLastAttempt = $intervalInSeconds >= $remainingSeconds;
            usleep((int) (min($intervalInSeconds, $remainingSeconds) * 1_000_000));
        }

        self::failWith(new AssertionFailure(
            "Failed asserting that the check passes within $timeoutInSeconds s ($attempts attempts). Last failure:" . PHP_EOL . '  ' . str_replace(PHP_EOL, PHP_EOL . '  ', $lastFailure),
            customMessage: $message,
        ));

        return null;
    }

    /**
     * Soft assertions: runs every assertion in $assertions even if some fail, then reports all
     * failures together. One run shows everything that is wrong with a result, not just the first.
     *
     * Assert::multiple(function () use ($user) {
     *     Assert::exact('Ada', $user->name);
     *     Assert::exact('ada@example.com', $user->email);
     * });
     *
     * @param callable(): mixed $assertions
     */
    public static function multiple(callable $assertions, string $message = ''): void
    {
        [$failures] = self::collectFailures($assertions);
        if ($failures === []) {
            return;
        }

        if (count($failures) === 1) {
            $failure = $failures[0];
            self::failWith($message === '' ? $failure : new AssertionFailure(
                $failure->message,
                $failure->expected,
                $failure->actual,
                $failure->diff,
                $failure->customMessage === '' ? $message : $message . PHP_EOL . $failure->customMessage,
            ));

            return;
        }

        $numbered = [];
        foreach ($failures as $index => $failure) {
            $details = $failure->fullMessage()
                . ($failure->hasExpected() ? PHP_EOL . 'Expected: ' . ValueExporter::export($failure->expected) : '')
                . ($failure->hasActual() ? PHP_EOL . 'Actual  : ' . ValueExporter::export($failure->actual) : '');
            $numbered[] = ($index + 1) . ') ' . str_replace(PHP_EOL, PHP_EOL . '   ', $details);
        }
        self::failWith(new AssertionFailure(count($failures) . ' assertions failed:' . PHP_EOL . implode(PHP_EOL, $numbered), customMessage: $message));
    }

    /** Skips the current test, e.g. when a service an integration test needs is not available. */
    public static function skip(string $reason = 'Skipped.'): never
    {
        throw new TestSkippedException($reason);
    }

    /** Skips the current test when $condition is true: Assert::skipIf(!extension_loaded('pdo_pgsql'), 'pdo_pgsql missing'). */
    public static function skipIf(bool $condition, string $reason): void
    {
        if ($condition) {
            throw new TestSkippedException($reason);
        }
    }

    /** Fails the current test unconditionally. */
    public static function fail(string $message = 'Test failed.'): never
    {
        throw new TestFailedException(new AssertionFailure($message));
    }
}
