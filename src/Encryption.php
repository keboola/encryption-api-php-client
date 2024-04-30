<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient;

class Encryption extends Common
{
    public function __construct(string $sapiToken, array $config)
    {
        parent::__construct(['X-StorageApi-Token' => $sapiToken], $config);
    }

    public function encryptPlainTextForConfiguration(
        string $value,
        string $projectId,
        string $componentId,
        string $configId
    ): string {
        $url = 'encrypt?';
        $url .= http_build_query([
            'projectId' => $projectId,
            'componentId' => $componentId,
            'configId' => $configId,
        ]);

        $result = $this->apiPost($url, ['#value' => $value]);
        return $result['#value'];
    }
}
