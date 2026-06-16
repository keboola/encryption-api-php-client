# Encryption API Client

## Installation
```bash
composer require keboola/encryption-api-php-client
```

## Development
Run the tests:

```bash
docker compose run dev composer install
docker compose run dev
```

## Usage

Both clients build on [`keboola/php-api-client-base`](https://github.com/keboola/php-api-client-base).
The constructor takes the API base URL and the auth token first, followed by optional, named
transport options (`logger`, `backoffMaxTries`, `connectTimeout`, `requestTimeout`, `userAgent`,
`requestHandler`).

### Encryption

```php
use Keboola\EncryptionApiClient\Encryption;

$encryption = new Encryption(
    'https://encryption.keboola.com',
    getenv('STORAGE_API_TOKEN'),
);

$cipher = $encryption->encryptPlainTextForConfiguration(
    'my secret value',
    'project-id',
    'keboola.data-apps',
    '123456',
);
```

### Migrations

```php
use Keboola\EncryptionApiClient\Migrations;

$migrations = new Migrations(
    'https://encryption.keboola.com',
    getenv('MANAGE_API_TOKEN'),
);

$result = $migrations->migrateConfiguration(
    sourceStorageApiToken: '...',
    destinationStack: 'connection.europe-west3.gcp.keboola.com',
    destinationStorageApiToken: '...',
    componentId: 'keboola.data-apps',
    configId: '123456',
    branchId: '102',
    dryRun: true,
);
```

On a failed request both clients throw `Keboola\ApiClientBase\Exception\ClientException`.

## License

MIT licensed, see [LICENSE](./LICENSE) file.
