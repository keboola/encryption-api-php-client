<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient\Tests;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\EncryptionApiClient\Exception\ClientException;
use Keboola\EncryptionApiClient\Migrations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class MigrationsTest extends TestCase
{
    public function testRetryCurlExceptionFail(): void
    {
        $mock = new MockHandler(
            [
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
            ],
            function (ResponseInterface $a) {
                // abusing the mockhandler here: override the mock response and throw a Request exception
                throw new RequestException(
                    'Encryption API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer, errno 104',
                    new Request('GET', 'https://example.com'),
                    null,
                    null,
                    [
                        'errno' => 56,
                        'error' => 'OpenSSL SSL_read: Connection reset by peer, errno 104',
                    ]
                );
            }
        );

        // Add the history middleware to the handler stack.
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $migrations = new Migrations(
            'some-token',
            ['handler' => $stack, 'url' => 'https://encryption.keboola.com', 'backoffMaxTries' => 2]
        );
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Encryption API error: Encryption API error: cURL error 56:');
        $migrations->migrateConfiguration(
            'some-token',
            'some-stack',
            'some-token',
            'keboola.some-component',
            '1234',
            '102',
        );
    }

    public function testRetryCurlException(): void
    {
        $mock = new MockHandler(
            [
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    '["Configuration with id \"1234\" successfully migrated to stack \"some-stack\"."]'
                ),
            ],
            function (ResponseInterface $a) {
                if ($a->getStatusCode() === 500) {
                    // abusing the mockhandler here: override the mock response and throw a Request exception
                    throw new RequestException(
                        'Encryption API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer, errno 104',
                        new Request('GET', 'https://example.com'),
                        null,
                        null,
                        [
                            'errno' => 56,
                            'error' => 'OpenSSL SSL_read: Connection reset by peer, errno 104',
                        ]
                    );
                }
            }
        );

        // Add the history middleware to the handler stack.
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $migrations = new Migrations(
            'some-token',
            ['handler' => $stack, 'url' => 'https://encryption.keboola.com']
        );
        $message = $migrations->migrateConfiguration(
            'some-token',
            'some-stack',
            'some-token',
            'keboola.some-component',
            '1234',
            '102',
        );
        self::assertSame('Configuration with id "1234" successfully migrated to stack "some-stack".', $message);
    }

    public function testRetryCurlExceptionWithoutContext(): void
    {
        $mock = new MockHandler(
            [
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
            ],
            function (ResponseInterface $a) {
                // abusing the mockhandler here: override the mock response and throw a Request exception
                throw new RequestException(
                    'Encryption API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer, errno 104',
                    new Request('GET', 'https://example.com'),
                    null,
                    null,
                    []
                );
            }
        );

        // Add the history middleware to the handler stack.
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $migrations = new Migrations(
            'some-token',
            ['handler' => $stack, 'url' => 'https://encryption.keboola.com']
        );
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Encryption API error: Encryption API error: cURL error 56:');
        $migrations->migrateConfiguration(
            'some-token',
            'some-stack',
            'some-token',
            'keboola.some-component',
            '1234',
            '102',
        );
    }
}
