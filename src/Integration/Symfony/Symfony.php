<?php

namespace MicroUnit\Integration\Symfony;

use MicroUnit\Integration\ForwardsToTestCase;
use MicroUnit\Integration\HostedTestCaseIntegration;

/**
 * Symfony's testing helpers inside MicroUnit tests; every call goes to the WebTestCase of the
 * running test:
 *
 *     $client = Symfony::client();
 *     $client->request('GET', '/products');
 *     Symfony::assertResponseIsSuccessful();
 *     $repository = Symfony::container()->get(ProductRepository::class);
 *
 * @method static void assertResponseIsSuccessful(string $message = '')
 * @method static void assertResponseStatusCodeSame(int $expectedCode, string $message = '')
 * @method static void assertResponseRedirects(?string $expectedLocation = null, ?int $expectedCode = null, string $message = '')
 * @method static void assertSelectorTextContains(string $selector, string $text, string $message = '')
 * @method static void assertSelectorExists(string $selector, string $message = '')
 * @method static void assertSelectorNotExists(string $selector, string $message = '')
 * @method static void assertSelectorCount(int $expectedCount, string $selector, string $message = '')
 * @method static void assertSelectorTextSame(string $selector, string $text, string $message = '')
 * @method static void assertSelectorTextNotContains(string $selector, string $text, string $message = '')
 * @method static void assertAnySelectorTextContains(string $selector, string $text, string $message = '')
 * @method static void assertPageTitleSame(string $expectedTitle, string $message = '')
 * @method static void assertPageTitleContains(string $expectedTitle, string $message = '')
 * @method static void assertResponseHeaderSame(string $headerName, string $expectedValue, string $message = '')
 * @method static void assertResponseHasHeader(string $headerName, string $message = '')
 * @method static void assertResponseFormatSame(?string $expectedFormat, string $message = '')
 * @method static void assertRouteSame(string $expectedRoute, array<string, mixed> $parameters = [], string $message = '')
 * @method static void assertBrowserCookieValueSame(string $name, string $expectedValue, bool $raw = false, string $path = '/', ?string $domain = null, string $message = '')
 * @method static void assertEmailCount(int $count, ?string $transport = null, string $message = '')
 * @method static \Symfony\Component\HttpKernel\KernelInterface bootKernel(array<string, mixed> $options = [])
 * @method static \Symfony\Bundle\FrameworkBundle\KernelBrowser getClient(?\Symfony\Component\BrowserKit\AbstractBrowser $newClient = null)
 */
final class Symfony
{
    use ForwardsToTestCase;

    /**
     * A BrowserKit client for the running test (boots the kernel).
     *
     * @param array<string, mixed> $options kernel options
     * @param array<string, mixed> $server server parameters for every request
     * @return \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    public static function client(array $options = [], array $server = []): object
    {
        /** @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client = HostedTestCaseIntegration::forward('createClient', [$options, $server]);

        return $client;
    }

    /**
     * The test service container, which can also return private services.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public static function container(): object
    {
        /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
        $container = HostedTestCaseIntegration::forward('getContainer', []);

        return $container;
    }
}
