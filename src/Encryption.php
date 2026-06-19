<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Keboola\ApiClientBase\ApiClient;
use Keboola\ApiClientBase\ApiClientOptions;
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
     * @param non-empty-string $storageToken
     * @param int<0, max> $backoffMaxTries
     */
    public function __construct(
        string $baseUrl,
        string $storageToken,
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
            new StorageApiTokenAuthenticator($storageToken),
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
        $url = 'encrypt?' . http_build_query([
            'projectId' => $projectId,
            'componentId' => $componentId,
            'configId' => $configId,
        ]);

        $response = $this->apiClient->sendRequestAndMapResponse(
            new Request(
                'POST',
                $url,
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
