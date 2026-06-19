<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient\Tests;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Keboola\ApiClientBase\Json;
use Keboola\EncryptionApiClient\Encryption;
use Keboola\EncryptionApiClient\Exception\ClientException;
use PHPUnit\Framework\TestCase;

class EncryptionTest extends TestCase
{
    use ApiClientTestTrait;

    private const BASE_URL = 'https://encryption.keboola.com';
    private const API_TOKEN = 'some-token';

    public function testEncryptPlainTextForConfiguration(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                Json::encodeArray(['#value' => 'KBC::ConfigSecure::abcdefghijkl']),
            ),
        ]);

        $client = new Encryption(
            self::BASE_URL,
            self::API_TOKEN,
            requestHandler: $requestHandler(...),
        );

        $result = $client->encryptPlainTextForConfiguration(
            'plainValue',
            'project-id',
            'keboola.component-id',
            'config-id',
        );

        self::assertSame('KBC::ConfigSecure::abcdefghijkl', $result);

        self::assertCount(1, $requestsHistory);
        self::assertRequestEquals(
            'POST',
            self::BASE_URL . '/encrypt?projectId=project-id&componentId=keboola.component-id&configId=config-id',
            [
                'Content-Type' => 'application/json',
                'X-StorageApi-Token' => self::API_TOKEN,
            ],
            Json::encodeArray(['#value' => 'plainValue']),
            $requestsHistory[0]['request'],
        );
    }

    public function testEmptyBaseUrlThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Base URL must be a non-empty string');

        new Encryption('', self::API_TOKEN); // @phpstan-ignore-line
    }

    public function testEmptyTokenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage API token must not be empty');

        new Encryption(self::BASE_URL, ''); // @phpstan-ignore-line
    }

    public function testClientErrorUsesEncryptionMessage(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                Json::encodeArray(['message' => 'Invalid configuration']),
            ),
        ]);

        $client = new Encryption(
            self::BASE_URL,
            self::API_TOKEN,
            backoffMaxTries: 0,
            requestHandler: $requestHandler(...),
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Encryption API error: Invalid configuration');

        $client->encryptPlainTextForConfiguration('plainValue', 'project-id', 'keboola.component-id', 'config-id');
    }
}
