<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Services\Providers\EvolutionApiProvider;
use App\Modules\WhatsApp\Services\Providers\FlaxxaApiProvider;
use App\Modules\WhatsApp\Services\Providers\MetaApiProvider;
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
