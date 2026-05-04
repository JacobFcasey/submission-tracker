<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;
use InvalidArgumentException;

class IntegrationManager
{
    /** @var array<string, IntegrationAdapterInterface> */
    private array $adapters;

    public function __construct(iterable $adapters = [])
    {
        $this->adapters = [];
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->provider()] = $adapter;
        }
    }

    public function adapter(string $provider): IntegrationAdapterInterface
    {
        if (! isset($this->adapters[$provider])) {
            throw new InvalidArgumentException("Unsupported provider: {$provider}");
        }

        return $this->adapters[$provider];
    }

    public function connect(IntegrationConnection $connection, array $credentials): IntegrationConnection
    {
        return $this->adapter($connection->provider)->connect($connection, $credentials);
    }

    public function sync(IntegrationConnection $connection): array
    {
        return $this->adapter($connection->provider)->sync($connection);
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        return $this->adapter($connection->provider)->healthCheck($connection);
    }
}

