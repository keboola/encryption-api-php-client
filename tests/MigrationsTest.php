<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Keboola\ApiClientBase\Json;
use Keboola\EncryptionApiClient\Exception\ClientException;
use Keboola\EncryptionApiClient\Migrations;
use PHPUnit\Framework\TestCase;

class MigrationsTest extends TestCase
{
    use ApiClientTestTrait;

    private const BASE_URL = 'https://encryption.keboola.com';
    private const API_TOKEN = 'some-token';

    private const SUCCESS_BODY = [
        'message' => 'Configuration with ID \'1234\' successfully migrated to stack \'some-stack\'.',
        'data' => [
            'destinationStack' => 'some-stack',
            'componentId' => 'sandboxes.data-apps',
            'configId' => '1234',
            'branchId' => '102',
        ],
    ];

    public function testMigrateConfiguration(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(200, ['Content-Type' => 'application/json'], Json::encodeArray(self::SUCCESS_BODY)),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            self::API_TOKEN,
            requestHandler: $requestHandler(...),
        );

        $result = $migrations->migrateConfiguration(
            'source-token',
            'some-stack',
            'destination-token',
            'keboola.some-component',
            '1234',
            '102',
        );

        self::assertSame(self::SUCCESS_BODY, $result);

        self::assertCount(1, $requestsHistory);
        self::assertRequestEquals(
            'POST',
            self::BASE_URL . '/migrate-configuration',
            [
                'Content-Type' => 'application/json',
                'X-KBC-ManageApiToken' => self::API_TOKEN,
            ],
            Json::encodeArray([
                'sourceStorageApiToken' => 'source-token',
                'destinationStack' => 'some-stack',
                'destinationStorageApiToken' => 'destination-token',
                'componentId' => 'keboola.some-component',
                'configId' => '1234',
                'branchId' => '102',
            ]),
            $requestsHistory[0]['request'],
        );
    }

    public function testMigrateConfigurationDryRun(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(200, ['Content-Type' => 'application/json'], Json::encodeArray(self::SUCCESS_BODY)),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            self::API_TOKEN,
            requestHandler: $requestHandler(...),
        );

        $migrations->migrateConfiguration(
            'source-token',
            'some-stack',
            'destination-token',
            'keboola.some-component',
            '1234',
            '102',
            dryRun: true,
        );

        self::assertCount(1, $requestsHistory);
        self::assertSame(
            self::BASE_URL . '/migrate-configuration?dry-run=true',
            $requestsHistory[0]['request']->getUri()->__toString(),
        );
    }

    public function testRetriesServerErrorThenSucceeds(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(500),
            new Response(500),
            new Response(200, ['Content-Type' => 'application/json'], Json::encodeArray(self::SUCCESS_BODY)),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            self::API_TOKEN,
            requestHandler: $requestHandler(...),
        );

        $result = $migrations->migrateConfiguration(
            'source-token',
            'some-stack',
            'destination-token',
            'keboola.some-component',
            '1234',
            '102',
        );

        self::assertSame(self::SUCCESS_BODY, $result);
        self::assertCount(3, $requestsHistory);
    }

    public function testRetriesTransportErrorThenSucceeds(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new ConnectException('Connection reset by peer', new Request('POST', 'migrate-configuration')),
            new Response(200, ['Content-Type' => 'application/json'], Json::encodeArray(self::SUCCESS_BODY)),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            self::API_TOKEN,
            requestHandler: $requestHandler(...),
        );

        $result = $migrations->migrateConfiguration(
            'source-token',
            'some-stack',
            'destination-token',
            'keboola.some-component',
            '1234',
            '102',
        );

        self::assertSame(self::SUCCESS_BODY, $result);
        self::assertCount(2, $requestsHistory);
    }

    public function testRetryExhaustionThrowsClientException(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(500),
            new Response(500),
            new Response(500),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            self::API_TOKEN,
            backoffMaxTries: 2,
            requestHandler: $requestHandler(...),
        );

        try {
            $migrations->migrateConfiguration(
                'source-token',
                'some-stack',
                'destination-token',
                'keboola.some-component',
                '1234',
                '102',
            );
            self::fail('Expected ClientException to be thrown');
        } catch (ClientException $e) {
            self::assertCount(3, $requestsHistory);
        }
    }

    public function testClientErrorUsesEncryptionMessage(): void
    {
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                Json::encodeArray(['message' => 'Configuration not found']),
            ),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            self::API_TOKEN,
            backoffMaxTries: 0,
            requestHandler: $requestHandler(...),
        );

        try {
            $migrations->migrateConfiguration(
                'source-token',
                'some-stack',
                'destination-token',
                'keboola.some-component',
                '1234',
                '102',
            );
            self::fail('Expected ClientException to be thrown');
        } catch (ClientException $e) {
            self::assertSame('Encryption API error: Configuration not found', $e->getMessage());
            self::assertSame(400, $e->getStatusCode());
            self::assertSame(Json::encodeArray(['message' => 'Configuration not found']), $e->getResponseBody());
        }
    }

    public function testEmptyManageTokenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manage API token must not be empty');

        new Migrations(self::BASE_URL, ''); // @phpstan-ignore-line
    }

    public function testNullManageTokenUsesServiceAccountAuth(): void
    {
        // With a null manage token the client authenticates via the projected Connection
        // service-account token. That token file is absent in the test environment, so the
        // service-account authenticator fails before any request is sent — which proves it
        // (not manage-token auth) was selected and that the request never left the client.
        $requestHandler = self::createRequestHandler($requestsHistory, [
            new Response(200, ['Content-Type' => 'application/json'], Json::encodeArray(self::SUCCESS_BODY)),
        ]);

        $migrations = new Migrations(
            self::BASE_URL,
            null,
            backoffMaxTries: 0,
            requestHandler: $requestHandler(...),
        );

        try {
            $migrations->migrateConfiguration(
                'source-token',
                'some-stack',
                'destination-token',
                'keboola.some-component',
                '1234',
                '102',
            );
            self::fail('Expected ClientException to be thrown');
        } catch (ClientException $e) {
            self::assertCount(0, $requestsHistory);
        }
    }
}
