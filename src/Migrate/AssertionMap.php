<?php

namespace MicroUnit\Migrate;

/**
 * How each PHPUnit assertion is written in MicroUnit. Argument order is the same in both (expected
 * first), so most assertions only change their name; the rest are spelled out as expressions.
 * Names are matched in lowercase: PHP method names are case-insensitive.
 */
final class AssertionMap
{
    /** PHPUnit name => MicroUnit Assert method with the same parameters. */
    private const array RENAMED = [
        'assertequals' => 'equals',
        'assertnotequals' => 'notEquals',
        'assertsame' => 'exact',
        'assertnotsame' => 'notExact',
        'asserttrue' => 'isTrue',
        'assertfalse' => 'isFalse',
        'assertnull' => 'isNull',
        'assertnotnull' => 'notNull',
        'assertinstanceof' => 'instanceOf',
        'assertcount' => 'countEquals',
        'assertempty' => 'empty',
        'assertnotempty' => 'notEmpty',
        'assertarrayhaskey' => 'hasKey',
        'assertarraynothaskey' => 'notHasKey',
        'assertcontains' => 'contains',
        'assertnotcontains' => 'notContains',
        'assertstringcontainsstring' => 'stringContains',
        'assertstringnotcontainsstring' => 'stringNotContains',
        'assertstringcontainsstringignoringcase' => 'stringContainsIgnoringCase',
        'assertstringstartswith' => 'stringStartsWith',
        'assertstringendswith' => 'stringEndsWith',
        'assertmatchesregularexpression' => 'matchesRegex',
        'assertregexp' => 'matchesRegex',
        'assertgreaterthan' => 'isGreaterThan',
        'assertgreaterthanorequal' => 'isGreaterThanOrEqual',
        'assertlessthan' => 'isLessThan',
        'assertlessthanorequal' => 'isLessThanOrEqual',
        'assertequalswithdelta' => 'equalsWithDelta',
        'assertequalscanonicalizing' => 'equivalent',
        'assertequalsignoringcase' => 'equalsIgnoringCase',
        'assertsamesize' => 'sameSize',
        'assertislist' => 'isList',
        'assertjson' => 'isJson',
        'assertjsonstringequalsjsonstring' => 'jsonEquals',
        'assertfileexists' => 'fileExists',
        'assertfiledoesnotexist' => 'fileNotExists',
        'assertfilenotexists' => 'fileNotExists',
        'assertdirectoryexists' => 'directoryExists',
        'assertdirectorydoesnotexist' => 'fileNotExists',
        'assertobjecthasproperty' => 'hasProperty',
        'assertobjecthasattribute' => 'hasProperty',
        'assertnan' => 'isNan',
        'assertfinite' => 'isFinite',
    ];

    /** assertIsX => the type name for Assert::isType(). */
    private const array TYPES = [
        'assertisarray' => 'array', 'assertisbool' => 'bool', 'assertisfloat' => 'float', 'assertisint' => 'int',
        'assertisnumeric' => 'numeric', 'assertisobject' => 'object', 'assertisresource' => 'resource',
        'assertisstring' => 'string', 'assertisscalar' => 'scalar', 'assertiscallable' => 'callable', 'assertisiterable' => 'iterable',
    ];

    /** assertIsNotX => the PHP is_* function. */
    private const array NEGATED_TYPES = [
        'assertisnotarray' => 'is_array', 'assertisnotbool' => 'is_bool', 'assertisnotfloat' => 'is_float', 'assertisnotint' => 'is_int',
        'assertisnotnumeric' => 'is_numeric', 'assertisnotobject' => 'is_object', 'assertisnotresource' => 'is_resource',
        'assertisnotstring' => 'is_string', 'assertisnotscalar' => 'is_scalar', 'assertisnotcallable' => 'is_callable', 'assertisnotiterable' => 'is_iterable',
    ];

    /**
     * PHPUnit parameter names by their place among an assertion's arguments: the expectation
     * comes first, then the value under test, then a delta. Named arguments are written in this
     * order without their names, since MicroUnit names its parameters differently; only
     * `message:` stays named.
     */
    private const array PARAMETER_ORDER = [
        'expected' => 0, 'expectedcount' => 0, 'key' => 0, 'needle' => 0, 'pattern' => 0, 'minimum' => 0, 'maximum' => 0,
        'expectedjson' => 0, 'expectedfile' => 0, 'propertyname' => 0, 'attributename' => 0, 'prefix' => 0, 'suffix' => 0,
        'format' => 0, 'type' => 0, 'classname' => 0,
        'actual' => 1, 'haystack' => 1, 'array' => 1, 'string' => 1, 'actualjson' => 1, 'actualfile' => 1, 'actualstring' => 1,
        'object' => 1, 'condition' => 1, 'filename' => 1, 'directory' => 1,
        'delta' => 2,
    ];

    private function __construct() {}

    public static function isAssertion(string $name): bool
    {
        return str_starts_with(strtolower($name), 'assert') && self::translate($name, ['$x', '$y', '$z', '$w']) !== null;
    }

    /**
     * The MicroUnit expression for a PHPUnit assertion call, or null when there is no translation.
     *
     * @param list<string> $arguments already translated argument texts (named arguments keep their "name:" prefix)
     */
    public static function translate(string $name, array $arguments): ?string
    {
        $name = strtolower($name);
        $arguments = self::positional($arguments);
        // PHPUnit's assertContains() has no $strict parameter before the message.
        if (in_array($name, ['assertcontains', 'assertnotcontains'], true) && isset($arguments[2]) && !str_starts_with($arguments[2], 'message:')) {
            $arguments[2] = 'message: ' . $arguments[2];
        }
        if (isset(self::RENAMED[$name])) {
            return 'Assert::' . self::RENAMED[$name] . '(' . implode(', ', $arguments) . ')';
        }
        if (isset(self::TYPES[$name])) {
            return "Assert::isType('" . self::TYPES[$name] . "', " . implode(', ', $arguments) . ')';
        }
        if (isset(self::NEGATED_TYPES[$name])) {
            return self::negated(self::NEGATED_TYPES[$name] . '(' . ($arguments[0] ?? '') . ')', array_slice($arguments, 1));
        }

        [$first, $second] = [$arguments[0] ?? '', $arguments[1] ?? ''];
        $rest = static fn(int $from): string => count($arguments) > $from ? ', ' . implode(', ', array_slice($arguments, $from)) : '';

        return match ($name) {
            'assertcontainsequals' => "Assert::contains($first, $second, false" . $rest(2) . ')',
            'assertnotcontainsequals' => "Assert::notContains($first, $second, false" . $rest(2) . ')',
            'assertcontainsonly' => "Assert::each($second, fn(\$element) => Assert::isType($first, \$element)" . $rest(2) . ')',
            'assertcontainsonlyinstancesof' => "Assert::each($second, fn(\$element) => Assert::instanceOf($first, \$element)" . $rest(2) . ')',
            'assertnotinstanceof' => self::negated(str_ends_with($first, '::class') ? "$second instanceof " . substr($first, 0, -7) : "is_a($second, $first)", array_slice($arguments, 2)),
            'assertnotcount' => "Assert::notExact($first, count($second)" . $rest(2) . ')',
            'assertnottrue' => "Assert::notExact(true, $first" . $rest(1) . ')',
            'assertnotfalse' => "Assert::notExact(false, $first" . $rest(1) . ')',
            'assertinfinite' => self::negated("is_finite($first)", array_slice($arguments, 1)),
            'assertdoesnotmatchregularexpression', 'assertnotregexp' => "Assert::exact(0, preg_match($first, $second)" . $rest(2) . ')',
            'assertstringnotcontainsstringignoringcase' => self::negated("str_contains(mb_strtolower($second), mb_strtolower($first))", array_slice($arguments, 2)),
            'assertstringnotstartswith' => self::negated("str_starts_with($second, $first)", array_slice($arguments, 2)),
            'assertstringnotendswith' => self::negated("str_ends_with($second, $first)", array_slice($arguments, 2)),
            'assertstringequalsfile' => "Assert::equals(file_get_contents($first), $second" . $rest(2) . ')',
            'assertfileequals' => "Assert::equals(file_get_contents($first), file_get_contents($second)" . $rest(2) . ')',
            'assertjsonstringequalsjsonfile' => "Assert::jsonEquals(file_get_contents($first), $second" . $rest(2) . ')',
            'assertjsonfileequalsjsonfile' => "Assert::jsonEquals(file_get_contents($first), file_get_contents($second)" . $rest(2) . ')',
            'assertisreadable' => "Assert::isTrue(is_readable($first)" . $rest(1) . ')',
            'assertiswritable' => "Assert::isTrue(is_writable($first)" . $rest(1) . ')',
            'assertfileisreadable' => "Assert::isTrue(is_file($first) && is_readable($first)" . $rest(1) . ')',
            'assertfileiswritable' => "Assert::isTrue(is_file($first) && is_writable($first)" . $rest(1) . ')',
            'assertdirectoryisreadable' => "Assert::isTrue(is_dir($first) && is_readable($first)" . $rest(1) . ')',
            'assertdirectoryiswritable' => "Assert::isTrue(is_dir($first) && is_writable($first)" . $rest(1) . ')',
            'assertobjectnothasproperty', 'assertobjectnothasattribute' => self::negated("property_exists($second, $first)", array_slice($arguments, 2)),
            'assertstringmatchesformat' => "Assert::matchesFormat($first, $second" . $rest(2) . ')',
            'assertobjectequals' => "Assert::isTrue($second->" . (isset($arguments[2]) ? trim($arguments[2], '\'"') : 'equals') . "($first))",
            default => null,
        };
    }

    /**
     * Named arguments (`expected: 1, actual: $x`) as positional ones in PHPUnit's order; a
     * message stays named and goes last.
     *
     * @param list<string> $arguments
     * @return list<string>
     */
    private static function positional(array $arguments): array
    {
        $positional = [];
        $named = [];
        $message = null;
        foreach ($arguments as $argument) {
            if (preg_match('/^(\w+)\s*:(?!:)\s*(.*)$/s', $argument, $match) !== 1) {
                $positional[] = $argument;
            } elseif (strtolower($match[1]) === 'message') {
                $message = $match[2];
            } else {
                $named[] = [self::PARAMETER_ORDER[strtolower($match[1])] ?? 1, $match[2]];
            }
        }
        if ($named === [] && $message === null) {
            return $arguments;
        }
        usort($named, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        return [...$positional, ...array_column($named, 1), ...($message === null ? [] : ["message: $message"])];
    }

    /** @param list<string> $message */
    private static function negated(string $condition, array $message): string
    {
        return "Assert::isFalse($condition" . ($message === [] ? '' : ', ' . implode(', ', $message)) . ')';
    }
}
