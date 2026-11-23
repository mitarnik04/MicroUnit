<?php

namespace MicroUnit\Integration\Laravel;

use MicroUnit\Integration\ForwardsToTestCase;
use MicroUnit\Integration\HostedTestCaseIntegration;

/**
 * Laravel's testing helpers inside MicroUnit tests. Every call goes to the Laravel test case of the
 * running test, so the full API of Laravel's testing layer is available:
 *
 *     Laravel::actingAs($admin)->postJson('/api/products', $payload)->assertCreated();
 *     Laravel::assertDatabaseHas('products', ['name' => 'Hub']);
 *
 * @method static \Illuminate\Testing\TestResponse get(string $uri, array<string, mixed> $headers = [])
 * @method static \Illuminate\Testing\TestResponse getJson(string $uri, array<string, mixed> $headers = [], int $options = 0)
 * @method static \Illuminate\Testing\TestResponse post(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [])
 * @method static \Illuminate\Testing\TestResponse postJson(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [], int $options = 0)
 * @method static \Illuminate\Testing\TestResponse put(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [])
 * @method static \Illuminate\Testing\TestResponse putJson(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [], int $options = 0)
 * @method static \Illuminate\Testing\TestResponse patch(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [])
 * @method static \Illuminate\Testing\TestResponse patchJson(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [], int $options = 0)
 * @method static \Illuminate\Testing\TestResponse delete(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [])
 * @method static \Illuminate\Testing\TestResponse deleteJson(string $uri, array<mixed> $data = [], array<string, mixed> $headers = [], int $options = 0)
 * @method static \Illuminate\Testing\TestResponse call(string $method, string $uri, array<string, mixed> $parameters = [], array<string, mixed> $cookies = [], array<string, mixed> $files = [], array<string, mixed> $server = [], ?string $content = null)
 * @method static \Illuminate\Foundation\Testing\TestCase actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $guard = null)
 * @method static \Illuminate\Foundation\Testing\TestCase be(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $guard = null)
 * @method static \Illuminate\Foundation\Testing\TestCase withHeaders(array<string, mixed> $headers)
 * @method static \Illuminate\Foundation\Testing\TestCase withHeader(string $name, string $value)
 * @method static \Illuminate\Foundation\Testing\TestCase withoutHeader(string $name)
 * @method static \Illuminate\Foundation\Testing\TestCase withToken(string $token, string $type = 'Bearer')
 * @method static \Illuminate\Foundation\Testing\TestCase withBasicAuth(string $username, string $password)
 * @method static \Illuminate\Foundation\Testing\TestCase withCookie(string $name, string $value)
 * @method static \Illuminate\Foundation\Testing\TestCase withSession(array<string, mixed> $data)
 * @method static \Illuminate\Foundation\Testing\TestCase from(string $url)
 * @method static \Illuminate\Foundation\Testing\TestCase withoutMiddleware(string|array<string>|null $middleware = null)
 * @method static \Illuminate\Foundation\Testing\TestCase withoutExceptionHandling(array<class-string<\Throwable>> $except = [])
 * @method static \Illuminate\Foundation\Testing\TestCase withExceptionHandling()
 * @method static void assertDatabaseHas(string $table, array<string, mixed> $data = [], ?string $connection = null)
 * @method static void assertDatabaseMissing(string $table, array<string, mixed> $data = [], ?string $connection = null)
 * @method static void assertDatabaseCount(string $table, int $count, ?string $connection = null)
 * @method static void assertDatabaseEmpty(string $table, ?string $connection = null)
 * @method static void assertSoftDeleted(\Illuminate\Database\Eloquent\Model|string $table, array<string, mixed> $data = [], ?string $connection = null)
 * @method static void assertNotSoftDeleted(\Illuminate\Database\Eloquent\Model|string $table, array<string, mixed> $data = [], ?string $connection = null)
 * @method static void assertModelExists(\Illuminate\Database\Eloquent\Model $model)
 * @method static void assertModelMissing(\Illuminate\Database\Eloquent\Model $model)
 * @method static void assertAuthenticated(?string $guard = null)
 * @method static void assertAuthenticatedAs(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $guard = null)
 * @method static \Illuminate\Foundation\Testing\TestCase seed(array<string>|string $class = 'Database\Seeders\DatabaseSeeder')
 * @method static void assertGuest(?string $guard = null)
 * @method static \Mockery\MockInterface mock(string $abstract, ?\Closure $mock = null)
 * @method static \Mockery\MockInterface partialMock(string $abstract, ?\Closure $mock = null)
 * @method static \Mockery\MockInterface spy(string $abstract, ?\Closure $mock = null)
 * @method static object instance(string $abstract, object $instance)
 * @method static \Illuminate\Testing\PendingCommand|int artisan(string $command, array<string, mixed> $parameters = [])
 * @method static mixed travelTo(\DateTimeInterface|\Closure|string|null $date, ?callable $callback = null)
 * @method static \Illuminate\Foundation\Testing\Wormhole travel(int $value)
 * @method static \DateTimeInterface travelBack()
 * @method static mixed freezeTime(?callable $callback = null)
 */
final class Laravel
{
    use ForwardsToTestCase;

    /** The application of the running test. */
    public static function app(): object
    {
        HostedTestCaseIntegration::current();

        /** @var \Illuminate\Foundation\Application $application */
        $application = \Illuminate\Container\Container::getInstance();

        return $application;
    }
}
