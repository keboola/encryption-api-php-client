<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Keboola\ApiClientBase\ApiClient;
use Keboola\ApiClientBase\ApiClientOptions;
use Keboola\ApiClientBase\Auth\ManageApiTokenAuthenticator;
use Keboola\ApiClientBase\Json;
use Keboola\EncryptionApiClient\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class Migrations
{
    private const FALLBACK_USER_AGENT = 'Keboola Encryption Migrations PHP Client';

    private ApiClient $apiClient;

    /**
     * @param non-empty-string $baseUrl
     * @param non-empty-string $manageApiToken
     * @param int<0, max> $backoffMaxTries
     */
    public function __construct(
        string $baseUrl,
        string $manageApiToken,
        ?LoggerInterface $logger = null,
        int $backoffMaxTries = ApiClientOptions::DEFAULT_BACKOFF_MAX_TRIES,
        int $connectTimeout = ApiClientOptions::DEFAULT_CONNECT_TIMEOUT,
        int $requestTimeout = ApiClientOptions::DEFAULT_REQUEST_TIMEOUT,
        string $userAgent = self::FALLBACK_USER_AGENT,
        null|Closure|HandlerStack $requestHandler = null,
    ) {
        Assert::stringNotEmpty($baseUrl, 'Base URL must be a non-empty string');

        $this->apiClient = new ApiClient(
            $baseUrl,
            new ManageApiTokenAuthenticator($manageApiToken),
            new ApiClientOptions(
                userAgent: $userAgent,
                backoffMaxTries: $backoffMaxTries,
                connectTimeout: $connectTimeout,
                requestTimeout: $requestTimeout,
                requestHandler: $requestHandler,
                logger: $logger,
            ),
            errorMessageResolver: new EncryptionErrorMessageResolver(),
            exceptionClass: ClientException::class,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function migrateConfiguration(
        string $sourceStorageApiToken,
        string $destinationStack,
        string $destinationStorageApiToken,
        string $componentId,
        string $configId,
        string $branchId,
        bool $dryRun = false,
    ): array {
        $url = 'migrate-configuration';
        if ($dryRun) {
            $url .= '?' . http_build_query(['dry-run' => 'true']);
        }

        return $this->apiClient->sendRequestAndMapResponse(
            new Request(
                'POST',
                $url,
                ['Content-Type' => 'application/json'],
                Json::encodeArray([
                    'sourceStorageApiToken' => $sourceStorageApiToken,
                    'destinationStack' => $destinationStack,
                    'destinationStorageApiToken' => $destinationStorageApiToken,
                    'componentId' => $componentId,
                    'configId' => $configId,
                    'branchId' => $branchId,
                ]),
            ),
            ArrayResponse::class,
        )->data;
    }
}
