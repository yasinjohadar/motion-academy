<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Providers\EvolutionApiProvider;
use App\Services\WhatsApp\Providers\FlaxxaApiProvider;
use App\Services\WhatsApp\Providers\MetaApiProvider;
use InvalidArgumentException;

class WhatsAppProviderFactory
{
    public static function create(string $provider, array $config): WhatsAppProviderService
    {
        return match ($provider) {
            'evolution' => new EvolutionApiProvider($config),
            'meta' => new MetaApiProvider($config),
            'flaxxa' => new FlaxxaApiProvider($config),
            default => throw new InvalidArgumentException("Unsupported WhatsApp provider: {$provider}"),
        };
    }
}
