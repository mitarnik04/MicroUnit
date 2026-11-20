<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Setup\TestSetup;
use MicroUnit\Tests\Fixtures\AssertionCounter;

$tester = TestSetup::getTester('BehaviourAssertions');

$tester->define('does_not_throw_returns_the_result', function (): void {
    Assert::exact(4, Assert::doesNotThrow(fn() => 2 + 2));
});

$tester->define('does_not_throw_fails_with_class_message_and_location', function (): void {
    $exception = Assert::throws(fn() => Assert::doesNotThrow(fn() => throw new RuntimeException('disk full')), TestFailedException::class);

    Assert::stringContains('RuntimeException: disk full at ' . __FILE__, (string) $exception->failure->actual);
    Assert::instanceOf(RuntimeException::class, $exception->getPrevious());
});

$tester->define('does_not_throw_lets_assertion_failures_through_unchanged', function (): void {
    Assert::throws(fn() => Assert::doesNotThrow(fn() => Assert::exact(1, 2)), TestFailedException::class, 'identical');
});

$tester->define('triggers_error_captures_deprecations_and_returns_the_message', function (): void {
    $message = Assert::triggersError(fn() => trigger_error('use run() instead', E_USER_DEPRECATED), E_USER_DEPRECATED);

    Assert::exact('use run() instead', $message);
});

$tester->define('triggers_error_matches_the_message', function (): void {
    Assert::triggersError(function (): void {
        trigger_error('first');
        trigger_error('second notice');
    }, E_USER_NOTICE, 'second');
    Assert::throws(fn() => Assert::triggersError(fn() => trigger_error('other', E_USER_WARNING), E_USER_WARNING, 'expected'), TestFailedException::class);
});

$tester->define('triggers_error_fails_when_nothing_is_triggered', function (): void {
    Assert::throws(fn() => Assert::triggersError(fn() => null, E_USER_DEPRECATED), TestFailedException::class, 'E_USER_DEPRECATED is triggered');
});

$tester->define('prints_compares_the_output_exactly', function (): void {
    Assert::prints(function (): void {
        echo "Hello\n";
        printf('%s!', 'Ada');
    }, "Hello\nAda!");
    Assert::throws(fn() => Assert::prints(fn() => print('Hi'), 'Hello'), TestFailedException::class);
});

$tester->define('eventually_retries_until_the_check_passes', function (): void {
    $attempts = 0;
    $result = Assert::eventually(function () use (&$attempts): string {
        $attempts++;
        Assert::isGreaterThanOrEqual(3, $attempts);

        return 'ready';
    }, timeoutInSeconds: 2, intervalInSeconds: 0.001);

    Assert::exact('ready', $result);
    Assert::exact(3, $attempts);
});

$tester->define('eventually_reports_the_last_failure_after_the_timeout', function (): void {
    Assert::throws(
        fn() => Assert::eventually(fn() => throw new RuntimeException('connection refused'), timeoutInSeconds: 0.05, intervalInSeconds: 0.01),
        TestFailedException::class,
        'RuntimeException: connection refused',
    );
});

$tester->define('multiple_reports_every_failure_of_the_block', function (): void {
    $failure = Assert::throws(fn() => Assert::multiple(function (): void {
        Assert::exact('Ada', 'Bob');
        Assert::isTrue(true);
        Assert::exact(36, 37);
    }), TestFailedException::class)->failure;

    Assert::stringStartsWith('2 assertions failed:', $failure->message);
    Assert::stringContains("Expected: 'Ada'", $failure->message);
    Assert::stringContains('Actual  : 37', $failure->message);
});

$tester->define('multiple_keeps_the_diff_of_a_single_failure', function (): void {
    $failure = Assert::throws(fn() => Assert::multiple(fn() => Assert::equals([1, 2], [1, 3])), TestFailedException::class)->failure;

    Assert::notNull($failure->diff);
});

$tester->define('multiple_passes_when_nothing_fails', function (): void {
    Assert::multiple(function (): void {
        Assert::isTrue(true);
        Assert::exact(1, 1);
    });
});

$tester->define('a_passing_eventually_counts_as_an_assertion', function (): void {
    Assert::exact(1, AssertionCounter::countedBy(fn() => Assert::eventually(fn() => 'mail')));
});

$tester->define('eventually_tries_once_more_at_the_deadline_instead_of_sleeping_past_it', function (): void {
    $attempts = 0;
    $started = microtime(true);
    Assert::throws(function () use (&$attempts): void {
        Assert::eventually(function () use (&$attempts): bool {
            $attempts++;

            return false;
        }, timeoutInSeconds: 0.05, intervalInSeconds: 1.0);
    }, TestFailedException::class, '(2 attempts)');

    Assert::exact(2, $attempts);
    Assert::isLessThan(0.5, microtime(true) - $started);
});

$tester->define('a_passing_throws_counts_as_one_assertion', function (): void {
    Assert::exact(1, AssertionCounter::countedBy(fn() => Assert::throws(fn() => throw new RuntimeException('x'), RuntimeException::class, 'x')));
});

$tester->define('multiple_keeps_its_message_with_a_single_failure', function (): void {
    $failure = Assert::throws(fn() => Assert::multiple(fn() => Assert::isTrue(false, 'active flag'), 'User is valid'), TestFailedException::class)->failure;

    Assert::exact('User is valid' . PHP_EOL . 'active flag', $failure->customMessage);
    Assert::exact('Failed asserting that value is true.', $failure->message);
});
