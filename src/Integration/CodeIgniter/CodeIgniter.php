<?php

namespace MicroUnit\Integration\CodeIgniter;

use MicroUnit\Integration\ForwardsToTestCase;

/**
 * CodeIgniter 4's testing helpers inside MicroUnit tests; every call goes to the CIUnitTestCase of
 * the running test (with the traits configured on CodeIgniterIntegration):
 *
 *     CodeIgniter::get('/products')->assertOK();
 *     CodeIgniter::seeInDatabase('products', ['name' => 'Hub']);
 *
 * @method static \CodeIgniter\Test\TestResponse get(string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\TestResponse post(string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\TestResponse put(string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\TestResponse patch(string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\TestResponse delete(string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\TestResponse options(string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\TestResponse call(string $method, string $path, ?array<string, mixed> $params = null)
 * @method static \CodeIgniter\Test\CIUnitTestCase withSession(?array<string, mixed> $values = null)
 * @method static \CodeIgniter\Test\CIUnitTestCase withHeaders(array<string, mixed> $headers = [])
 * @method static \CodeIgniter\Test\CIUnitTestCase withBody(?string $body)
 * @method static \CodeIgniter\Test\CIUnitTestCase withBodyFormat(string $format)
 * @method static \CodeIgniter\Test\CIUnitTestCase withRoutes(?list<list<mixed>> $routes = null)
 * @method static \CodeIgniter\Test\CIUnitTestCase skipEvents()
 * @method static void seed(string $name)
 * @method static void seeInDatabase(string $table, array<string, mixed> $where)
 * @method static void dontSeeInDatabase(string $table, array<string, mixed> $where)
 * @method static void seeNumRecords(int $expected, string $table, array<string, mixed> $where)
 * @method static mixed grabFromDatabase(string $table, string $column, array<string, mixed> $where)
 * @method static int|string|bool hasInDatabase(string $table, array<string, mixed> $data)
 */
final class CodeIgniter
{
    use ForwardsToTestCase;
}
