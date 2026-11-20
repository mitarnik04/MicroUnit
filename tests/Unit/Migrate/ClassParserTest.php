<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Migrate\ClassParser;
use MicroUnit\Migrate\Code;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('ClassParser');

$tester->define('reads_properties_constants_and_methods', function (): void {
    [$class] = ClassParser::parse(<<<'PHP'
        <?php
        namespace Tests;
        final class CartTest extends TestCase
        {
            private const int LIMIT = 3, MAX = [1, 2][0];
            private ?Cart $cart = null, $other;
            protected static array $items = ['a' => [1, 2]];

            public function testTotal(int $a = 1): int
            {
                return $a;
            }
        }
        PHP, 'CartTest.php');

    Assert::exact('Tests\CartTest', $class->fullName());
    Assert::exact([['name' => 'LIMIT', 'value' => '3'], ['name' => 'MAX', 'value' => '[1, 2][0]']], $class->constants);
    Assert::exact(['cart', 'other', 'items'], array_map(static fn($property): string => $property->name, $class->properties));
    Assert::exact('?Cart', $class->properties[1]->type);
    Assert::isNull($class->properties[1]->default);
    Assert::exact("['a' => [1, 2]]", $class->properties[2]->default);
    Assert::isTrue($class->properties[2]->isStatic);
    Assert::exact('int $a = 1', $class->methods[0]->parameters);
    Assert::isTrue($class->methods[0]->isTest());
});

$tester->define('reads_classes_in_braced_namespaces', function (): void {
    $classes = ClassParser::parse(<<<'PHP'
        <?php
        namespace Helpers {
            class Tester {}
        }
        namespace Tests {
            use PHPUnit\Framework\TestCase;
            final class CartTest extends TestCase {}
        }
        PHP, 'CartTest.php');

    Assert::exact(['Helpers\Tester', 'Tests\CartTest'], array_map(static fn($class): string => $class->fullName(), $classes));
    Assert::exact([], $classes[0]->uses);
    Assert::exact(['use PHPUnit\Framework\TestCase'], $classes[1]->uses);
});

$tester->define('declarations_know_their_namespace_and_imports', function (): void {
    $declarations = ClassParser::declarations(Code::tokenize(<<<'PHP'
        <?php
        namespace Acme;
        use Foo\Bar;
        function helper() {}
        namespace Tests;
        final class CartTest {}
        PHP));

    Assert::exact(['Acme\helper', 'Tests\CartTest'], array_column($declarations, 'fullName'));
    Assert::exact(['Acme', 'Tests'], array_column($declarations, 'namespace'));
    Assert::exact([['use Foo\Bar'], []], array_column($declarations, 'uses'));
});

$tester->define('test_with_data_sets_continue_on_the_following_lines', function (): void {
    [$class] = ClassParser::parse(<<<'PHP'
        <?php
        class CartTest
        {
            /**
             * Adds.
             *
             * @testWith [1, 2]
             *           ["a", "b"]
             * @group slow
             */
            public function testAdd($a, $b) {}
        }
        PHP, 'CartTest.php');

    Assert::exact(['[1, 2]', '["a", "b"]'], $class->methods[0]->testWithDataSets());
});
