<?php

use MicroUnit\Assertion\Assert;
use MicroUnit\Migrate\ClassParser;
use MicroUnit\Migrate\MigratedSuite;
use MicroUnit\Migrate\SuiteTranslator;
use MicroUnit\Migrate\TestClass;
use MicroUnit\Setup\TestSetup;

$tester = TestSetup::getTester('SuiteTranslator');

/** Translates one PHPUnit class (source code) and checks that the result is valid PHP. */
function migratedSuite(string $phpUnitCode): MigratedSuite
{
    $classes = ClassParser::parse($phpUnitCode, 'C:/project/tests/CartTest.php');
    $index = [];
    foreach ($classes as $class) {
        $index[strtolower($class->fullName())] = $class;
    }
    $suite = SuiteTranslator::translate(TestClass::merge(end($classes), $index));
    PhpToken::tokenize($suite->code, TOKEN_PARSE); // throws on invalid code

    return $suite;
}

function migrated(string $phpUnitCode): string
{
    return migratedSuite($phpUnitCode)->code;
}

$tester->define('assertions_keep_their_arguments_under_microunit_names', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testTotals(): void
            {
                $this->assertSame(3, 1 + 2);
                self::assertCount(2, [1, 2], 'two items');
                $this->assertStringContainsString('ab', 'abc');
            }
        }
        PHP);

    Assert::stringContains("TestSetup::getTester('Cart')", $code);
    Assert::stringContains("\$tester->define('totals', function (): void {", $code);
    Assert::stringContains('Assert::exact(3, 1 + 2);', $code);
    Assert::stringContains("Assert::countEquals(2, [1, 2], 'two items');", $code);
    Assert::stringContains("Assert::stringContains('ab', 'abc');", $code);
});

$tester->define('properties_become_fixtures_and_helpers_functions', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use App\Cart;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private Cart $cart;

            protected function setUp(): void
            {
                $this->cart = new Cart();
            }

            public function testAddsItems(): void
            {
                $this->cart->add($this->product());
                $this->assertCount(1, $this->cart->items());
            }

            private function product(): string
            {
                return 'book';
            }
        }
        PHP);

    Assert::stringContains("\$tester->fixture('cart', fn(): Cart => new Cart());", $code);
    Assert::stringContains("\$tester->define('adds_items', function (Cart \$cart): void {", $code);
    Assert::stringContains('$cart->add(product());', $code);
    Assert::stringContains('function product(): string', $code);
    Assert::stringContains('use App\Cart;', $code);
});

$tester->define('data_providers_become_data_sets', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\Attributes\DataProvider;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            #[DataProvider('sums')]
            public function testAdds(int $a, int $b, int $sum): void
            {
                $this->assertSame($sum, $a + $b);
            }

            public static function sums(): array
            {
                return ['small' => [1, 2, 3]];
            }
        }
        PHP);

    Assert::stringContains("\$tester->defineGroup('adds', function (int \$a, int \$b, int \$sum): void {", $code);
    Assert::stringContains('}, sums());', $code);
    Assert::stringContains('function sums(): array', $code);
});

$tester->define('expected_exceptions_become_assert_throws', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testRejectsNegatives(): void
            {
                $this->expectException(\InvalidArgumentException::class);
                $this->expectExceptionMessage('negative');
                (new \App\Cart())->add(-1);
            }
        }
        PHP);

    Assert::stringContains("Assert::throws(fn() => (new \\App\\Cart())->add(-1), \\InvalidArgumentException::class, 'negative');", $code);
});

$tester->define('phpunit_mocks_become_microunit_mocks', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use App\Mailer;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testSendsMail(): void
            {
                $mailer = $this->createMock(Mailer::class);
                $mailer->expects($this->once())->method('send')->with('ada@example.com')->willReturn(true);
                $mailer->send('ada@example.com');
            }
        }
        PHP);

    Assert::stringContains('$mailer = MockBuilder::create(Mailer::class)->disableOriginalConstructor()->build()->newInstance();', $code);
    Assert::stringContains("MicroMock::of(\$mailer)->returns('send', true);", $code);
    Assert::stringContains("AssertMock::begin(\$mailer)->isCalledOnce('send')->isOnlyCalledWithMatchingArgs('send', fn(array \$arguments): bool => (\$arguments[0] ?? null) == 'ada@example.com');", $code);
});

$tester->define('untranslatable_tests_are_kept_as_skipped_tests_with_the_original', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            #[\PHPUnit\Framework\Attributes\Depends('testA')]
            public function testB(): void
            {
                $this->assertTrue(true);
            }
        }
        PHP);

    Assert::stringContains("Assert::skip('Needs manual migration: depends on another test", $code);
    Assert::stringContains('// Original PHPUnit code:', $code);
});

$tester->define('before_class_methods_with_any_name_run_once_like_set_up_before_class', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\Attributes\BeforeClass;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private static array $prices;

            #[BeforeClass]
            public static function loadPrices(): void
            {
                self::$prices = ['keyboard' => 4900];
            }

            public function testKnowsPrices(): void
            {
                $this->assertSame(4900, self::$prices['keyboard']);
            }
        }
        PHP);

    Assert::stringNotContains('function loadPrices', $code);
    Assert::stringContains("Assert::exact(4900, \$prices['keyboard']);", $code);
    Assert::stringContains('shared: true', $code);
});

$tester->define('add_to_assertion_count_counts_assertions', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testChecksByHand(): void
            {
                $this->addToAssertionCount(1);
            }
        }
        PHP);

    Assert::stringContains('\MicroUnit\Core\TestContext::countAssertions(1);', $code);
});

$tester->define('tear_down_drops_statements_that_only_reset_properties', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private Cart $cart;

            protected function setUp(): void
            {
                $this->cart = new Cart();
            }

            protected function tearDown(): void
            {
                unset($this->cart);
            }

            public function testIsEmpty(): void
            {
                $this->assertSame(0, $this->cart->count());
            }
        }
        PHP);

    Assert::stringNotContains('tearDown', $code);
    Assert::stringNotContains('unset', $code);
});

$tester->define('a_provider_named_by_attribute_and_annotation_is_used_once', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\Attributes\DataProvider;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            /** @dataProvider prices */
            #[DataProvider('prices')]
            public function testPrice(int $price): void
            {
                $this->assertGreaterThan(0, $price);
            }

            public static function prices(): array
            {
                return [[1], [2]];
            }
        }
        PHP);

    Assert::stringContains('}, prices());', $code);
});

$tester->define('a_set_up_local_shared_by_two_fixtures_becomes_a_fixture_both_receive', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private Repo $repo;
            private Service $service;

            protected function setUp(): void
            {
                $connection = new Connection();
                $this->repo = new Repo($connection);
                $this->service = new Service($connection);
            }

            public function testShares(): void
            {
                $this->assertSame($this->repo, $this->service->repo());
            }
        }
        PHP);

    Assert::exact(1, substr_count($code, 'new Connection()'));
    Assert::stringContains("\$tester->fixture('connection', fn() => new Connection());", $code);
    Assert::stringContains("\$tester->fixture('repo', fn(\$connection): Repo => new Repo(\$connection));", $code);
    Assert::stringContains("\$tester->fixture('service', fn(\$connection): Service => new Service(\$connection));", $code);
});

$tester->define('a_set_up_statement_is_not_repeated_in_the_factory_that_runs_it', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private array $defaults = [];
            private Repo $repo;

            protected function setUp(): void
            {
                $connection = new Connection();
                $this->repo = new Repo($connection);
            }

            public function testRepo(): void
            {
                $this->assertNotNull($this->repo);
            }
        }
        PHP);

    Assert::exact(1, substr_count($code, 'new Connection()'));
});

$tester->define('tear_down_after_class_code_about_a_shared_fixture_becomes_its_cleanup', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private static string $dir;

            public static function setUpBeforeClass(): void
            {
                self::$dir = sys_get_temp_dir() . '/cart';
            }

            public static function tearDownAfterClass(): void
            {
                rmdir(self::$dir);
            }

            public function testDir(): void
            {
                $this->assertNotEmpty(self::$dir);
            }
        }
        PHP);

    Assert::stringContains("cleanup: function (string \$dir): void {\n    rmdir(\$dir);\n}, shared: true);", $code);
    Assert::stringNotContains('tearDownOnce', $code);
});

$tester->define('once_hooks_using_shared_fixtures_are_reported', function (): void {
    $suite = migratedSuite(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private static string $a;
            private static string $b;

            public static function setUpBeforeClass(): void
            {
                self::$a = 'a';
                self::$b = 'b';
            }

            public static function tearDownAfterClass(): void
            {
                compare(self::$a, self::$b);
            }

            public function testA(): void
            {
                $this->assertSame('a', self::$a);
            }
        }
        PHP);

    Assert::stringContains('teardownafterclass() uses $a, $b, which became shared fixtures', implode("\n", $suite->issues));
});

$tester->define('static_properties_changed_through_a_reference_stay_variables', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private static array $items = [];
            private static int $limit = 3;

            public function testPush(): void
            {
                array_push(self::$items, 1);
                $this->assertCount(1, self::$items);
                $this->assertSame(3, self::$limit);
            }
        }
        PHP);

    Assert::stringContains('array_push($items, 1);', $code);
    Assert::stringContains('Assert::exact(3, (3));', $code);
});

$tester->define('a_method_named_method_on_an_object_that_is_no_mock_is_kept', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testRoute(): void
            {
                $route = new Route();
                $route->method('POST');
                $this->assertSame('POST', $route->getMethod());
            }
        }
        PHP);

    Assert::stringContains("\$route->method('POST');", $code);
});

$tester->define('stubbing_a_mock_fixture_without_a_return_value_is_dropped', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            private Mailer $mailer;

            protected function setUp(): void
            {
                $this->mailer = $this->createMock(Mailer::class);
            }

            public function testSends(): void
            {
                $this->mailer->method('send');
                $this->assertNotNull($this->mailer);
            }
        }
        PHP);

    Assert::stringNotContains("->method('send')", $code);
});

$tester->define('an_expectation_helper_called_from_another_helper_is_reported', function (): void {
    $suite = migratedSuite(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testRejects(): void
            {
                $this->prepareRejection();
                throw new \InvalidArgumentException();
            }

            private function prepareRejection(): void
            {
                $this->reset();
                $this->expectInvalid();
            }

            private function reset(): void
            {
            }

            private function expectInvalid(): void
            {
                $this->expectException(\InvalidArgumentException::class);
            }
        }
        PHP);

    Assert::stringContains('prepareRejection() calls expectInvalid(), which only sets expectations', implode("\n", $suite->issues));
    Assert::stringContains("throw new \\LogicException('Needs manual migration:", $suite->code);
});

$tester->define('test_with_annotations_keep_every_data_set_line', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            /**
             * @testWith [0, 0]
             *           [1, 1]
             */
            public function testSame(int $a, int $b): void
            {
                $this->assertSame($a, $b);
            }
        }
        PHP);

    Assert::stringContains("json_decode('[0, 0]', true),\n    json_decode('[1, 1]', true),", $code);
});

$tester->define('external_data_providers_are_called_through_an_import', function (): void {
    $suite = migratedSuite(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\Attributes\DataProviderExternal;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            #[DataProviderExternal(Prices::class, 'all')]
            public function testPrice(int $price): void
            {
                $this->assertGreaterThan(0, $price);
            }
        }
        PHP);

    Assert::exact(['price'], $suite->migratedTests);
    Assert::stringContains('}, Prices::all());', $suite->code);
    Assert::stringContains('use Tests\Prices;', $suite->code);
});

$tester->define('requirements_of_class_and_annotations_become_skip_if', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('intl')]
        final class CartTest extends TestCase
        {
            /** @requires PHP >= 8.1 */
            public function testFormats(): void
            {
                $this->assertTrue(true);
            }
        }
        PHP);

    Assert::stringContains("Assert::skipIf(!extension_loaded('intl'), 'Requires the ' . 'intl' . ' extension.');", $code);
    Assert::stringContains("Assert::skipIf(!version_compare(PHP_VERSION, '8.1', '>='), 'Requires PHP >= 8.1.');", $code);
});

$tester->define('requirements_without_a_translation_need_manual_work', function (): void {
    $suite = migratedSuite(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\Attributes\RequiresPhp;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            #[RequiresPhp('^8.1')]
            public function testFormats(): void
            {
                $this->assertTrue(true);
            }
        }
        PHP);

    Assert::exact(["has #[RequiresPhp('^8.1')], which migrate cannot translate."], $suite->skippedTests['formats']);
});

$tester->define('named_assertion_arguments_become_positional_and_messages_stay_messages', function (): void {
    $code = migrated(<<<'PHP'
        <?php
        namespace Tests;
        use PHPUnit\Framework\TestCase;
        final class CartTest extends TestCase
        {
            public function testCounts(): void
            {
                $this->assertCount(haystack: [1], expectedCount: 1);
                $this->assertContains(1, [1], 'has one');
            }
        }
        PHP);

    Assert::stringContains('Assert::countEquals(1, [1]);', $code);
    Assert::stringContains("Assert::contains(1, [1], message: 'has one');", $code);
});
