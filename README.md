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

The manage token is optional. When it is omitted (or `null`), `Migrations` authenticates with the
projected Connection service-account token instead — useful for in-cluster service-to-service calls:

```php
$migrations = new Migrations('https://encryption.keboola.com'); // service-account auth
```

On a failed request both clients throw `Keboola\EncryptionApiClient\Exception\ClientException`
(a subclass of `Keboola\ApiClientBase\Exception\ClientException`). The exception exposes
`getStatusCode()` and `getResponseBody()` for the failing response, when available.

## License

MIT licensed, see [LICENSE](./LICENSE) file.
