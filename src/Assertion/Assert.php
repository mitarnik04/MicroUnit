<?php

namespace MicroUnit\Assertion;

use MicroUnit\Assertion\Assert\BehaviourAssertions;
use MicroUnit\Assertion\Assert\CollectionAssertions;
use MicroUnit\Assertion\Assert\FileAssertions;
use MicroUnit\Assertion\Assert\NumberAssertions;
use MicroUnit\Assertion\Assert\SnapshotAssertions;
use MicroUnit\Assertion\Assert\StringAssertions;
use MicroUnit\Assertion\Assert\ValueAssertions;
use MicroUnit\Core\TestContext;
use MicroUnit\Exceptions\TestFailedException;
use MicroUnit\Helpers\Diff;

/**
 * Static assertions. Argument order follows PHPUnit: the expected value comes first, the actual
 * value last, and every assertion accepts an optional custom failure message as final argument.
 * Assertions about behaviour take the callable first: Assert::throws(fn () => ..., Type::class).
 *
 * The methods live in topic traits (values, numbers, collections, strings, files, behaviour,
 * snapshots); this class only holds what they share.
 */
final class Assert
{
    use ValueAssertions;
    use NumberAssertions;
    use CollectionAssertions;
    use StringAssertions;
    use FileAssertions;
    use BehaviourAssertions;
    use SnapshotAssertions;

    /** @var list<AssertionFailure>|null failures collected inside Assert::multiple(), null outside of it */
    private static ?array $softFailures = null;

    private function __construct() {}

    /**
     * Counts the assertion and fails it unless $condition holds. Only for failure details that
     * cost nothing to pass along; anything that has to be computed (interpolated numbers, array_keys(),
     * diffs, ...) belongs after `if (!self::passes(...))`, so the passing path never pays for it.
     */
    private static function check(
        bool $condition,
        string $description,
        mixed $expected = NotProvided::Value,
        mixed $actual = NotProvided::Value,
        bool $withDiff = false,
        string $customMessage = '',
    ): void {
        if (!self::passes($condition)) {
            self::reject($description, $expected, $actual, $withDiff, $customMessage);
        }
    }

    /** Counts the assertion and returns $condition. */
    private static function passes(bool $condition): bool
    {
        TestContext::countAssertions();

        return $condition;
    }

    /** Fails the running assertion; $withDiff diffs $expected against $actual, $diff is a ready-made one. */
    private static function reject(
        string $description,
        mixed $expected = NotProvided::Value,
        mixed $actual = NotProvided::Value,
        bool $withDiff = false,
        string $customMessage = '',
        ?Diff $diff = null,
    ): void {
        self::failWith(new AssertionFailure(
            $description,
            $expected,
            $actual,
            $diff ?? ($withDiff ? Diff::generate($expected, $actual) : null),
            $customMessage,
        ));
    }

    /**
     * Throws the failure, or records it while Assert::multiple() collects every failure of a block.
     *
     * @internal for MicroUnit's other assertion classes (AssertMock, AssertResponse)
     */
    public static function failWith(AssertionFailure $failure): void
    {
        if (self::$softFailures === null) {
            throw new TestFailedException($failure);
        }
        self::$softFailures[] = $failure;
    }

    /**
     * Runs $block and returns the assertion failures it produced instead of throwing them, plus
     * its return value. Nests: an enclosing Assert::multiple() keeps its own collection.
     *
     * @return array{list<AssertionFailure>, mixed}
     */
    private static function collectFailures(callable $block, mixed ...$arguments): array
    {
        $outer = self::$softFailures;
        self::$softFailures = [];
        $returned = null;
        try {
            $returned = $block(...$arguments);
        } catch (TestFailedException $exception) {
            self::$softFailures[] = $exception->failure;
        } finally {
            $failures = self::$softFailures;
            self::$softFailures = $outer;
        }

        return [$failures, $returned];
    }

    /** @param list<AssertionFailure> $failures */
    private static function describeFailures(array $failures, string $indent = '  '): string
    {
        return implode(PHP_EOL, array_map(
            static fn(AssertionFailure $failure): string => $indent . str_replace(PHP_EOL, PHP_EOL . $indent, $failure->fullMessage()),
            $failures,
        ));
    }

    /**
     * == for values, but objects of the same class compare property by property (like PHPUnit's
     * assertEquals): PHP's own == refuses some objects, DateInterval for example. Dates compare as
     * moments in time, closures by identity. Object graphs with cycles compare fine: a pair of
     * objects met again while it is still being compared counts as equal, the rest decides.
     *
     * @param array<string, true> $pairsInProgress "id:id" of the object pairs being compared further up
     */
    private static function looselyEqual(mixed $expected, mixed $actual, array &$pairsInProgress = []): bool
    {
        if ($expected === $actual) {
            return true; // identical implies equal; skips the recursion for the common passing case
        }
        if (is_object($expected) && is_object($actual)) {
            if ($expected::class !== $actual::class) {
                return false;
            }
            if ($expected instanceof \DateTimeInterface || $expected instanceof \Closure) {
                return $expected == $actual;
            }
            $pair = spl_object_id($expected) . ':' . spl_object_id($actual);
            if (isset($pairsInProgress[$pair])) {
                return true;
            }
            $pairsInProgress[$pair] = true;
            $isEqual = self::looselyEqual((array) $expected, (array) $actual, $pairsInProgress);
            unset($pairsInProgress[$pair]);

            return $isEqual;
        }
        if (is_array($expected) && is_array($actual)) {
            if (count($expected) !== count($actual)) {
                return false;
            }
            foreach ($expected as $key => $value) {
                if (!array_key_exists($key, $actual) || !self::looselyEqual($value, $actual[$key], $pairsInProgress)) {
                    return false;
                }
            }

            return true;
        }

        return $expected == $actual;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return match (true) {
            is_countable($value) => count($value) === 0,
            is_string($value) => $value === '',
            default => empty($value),
        };
    }

    /** @param iterable<mixed> $haystack */
    private static function iterableContains(iterable $haystack, mixed $needle, bool $strict): bool
    {
        if (is_array($haystack)) {
            return in_array($needle, $haystack, $strict);
        }
        foreach ($haystack as $element) {
            if ($strict ? $element === $needle : $element == $needle) {
                return true;
            }
        }

        return false;
    }
}
