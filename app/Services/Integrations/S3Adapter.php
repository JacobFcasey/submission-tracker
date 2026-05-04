<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;

class S3Adapter implements IntegrationAdapterInterface
{
    public function provider(): string
    {
        return 's3';
    }

    public function connect(IntegrationConnection $connection, array $credentials): IntegrationConnection
    {
        $connection->credentials_encrypted = $credentials;
        $connection->status = 'connected';
        $connection->save();

        return $connection;
    }

    public function sync(IntegrationConnection $connection): array
    {
        $connection->last_synced_at = now();
        $connection->status = 'connected';
        $connection->save();

        return ['provider' => $this->provider(), 'synced' => true];
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        return [
            'provider' => $this->provider(),
            'status' => $connection->status,
            'last_synced_at' => optional($connection->last_synced_at)->toISOString(),
        ];
    }
}

