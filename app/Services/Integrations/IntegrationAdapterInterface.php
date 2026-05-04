<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;

interface IntegrationAdapterInterface
{
    public function provider(): string;

    public function connect(IntegrationConnection $connection, array $credentials): IntegrationConnection;

    public function sync(IntegrationConnection $connection): array;

    public function healthCheck(IntegrationConnection $connection): array;
}

