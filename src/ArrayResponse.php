<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient;

use Keboola\ApiClientBase\ResponseModelInterface;

/**
 * Minimal response model that carries the decoded response body as-is.
 *
 * The base ApiClient only returns void or a {@see ResponseModelInterface}; this shim lets the
 * clients keep exposing the raw decoded array (and values extracted from it) as their public API.
 */
final class ArrayResponse implements ResponseModelInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromResponseData(array $data): static
    {
        return new self($data);
    }
}
