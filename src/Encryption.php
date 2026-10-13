<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Keboola\ApiClientBase\ApiClient;
use Keboola\ApiClientBase\ApiClientOptions;
use Keboola\ApiClientBase\Auth\NoAuthAuthenticator;
use Keboola\ApiClientBase\Auth\StorageApiTokenAuthenticator;
use Keboola\ApiClientBase\Json;
use Keboola\EncryptionApiClient\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class Encryption
{
    private const FALLBACK_USER_AGENT = 'Keboola Encryption PHP Client';

    private ApiClient $apiClient;

    /**
     * @param non-empty-string $baseUrl
     * @param non-empty-string|null $storageToken Optional, the encrypt endpoint is not authenticated.
     * @param int<0, max> $backoffMaxTries
     */
    public function __construct(
        string $baseUrl,
        ?string $storageToken = null,
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
            $storageToken !== null ? new StorageApiTokenAuthenticator($storageToken) : new NoAuthAuthenticator(),
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

    public function encryptPlainTextForConfiguration(
        string $value,
        string $projectId,
        string $componentId,
        string $configId,
    ): string {
        return $this->encryptPlainText($value, [
            'projectId' => $projectId,
            'componentId' => $componentId,
            'configId' => $configId,
        ]);
    }

    /**
     * Encrypts a value for the whole project, optionally limited to a branch type.
     */
    public function encryptPlainTextForProject(
        string $value,
        string $projectId,
        ?BranchType $branchType = null,
    ): string {
        return $this->encryptPlainText($value, [
            'projectId' => $projectId,
            'branchType' => $branchType?->value,
        ]);
    }

    /**
     * @param array<string, string|null> $query
     */
    private function encryptPlainText(string $value, array $query): string
    {
        $response = $this->apiClient->sendRequestAndMapResponse(
            new Request(
                'POST',
                'encrypt?' . http_build_query($query),
                ['Content-Type' => 'application/json'],
                // the API works with JSON requests/responses, so the value is wrapped in an object
                Json::encodeArray(['#value' => $value]),
            ),
            ArrayResponse::class,
        );

        $encrypted = $response->data['#value'] ?? null;
        Assert::string($encrypted, 'Encryption API response is missing the "#value" field');

        return $encrypted;
    }
}
