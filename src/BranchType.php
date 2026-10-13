<?php

declare(strict_types=1);

namespace Keboola\EncryptionApiClient;

enum BranchType: string
{
    case DEFAULT = 'default';
    case DEV = 'dev';
}
