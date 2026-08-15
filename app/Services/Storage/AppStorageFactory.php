<?php

namespace App\Services\Storage;

use App\Models\AppStorageConfig;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AppStorageFactory
{
    public static function create(AppStorageConfig $config): Filesystem
    {
        $driverConfig = $config->getDecryptedConfig();
        $diskName = 'app_storage_'.$config->id.'_'.md5(json_encode($driverConfig));

        $diskConfig = self::buildDiskConfig($config->driver, $driverConfig);

        if ($config->cdn_url) {
            $diskConfig['url'] = rtrim($config->cdn_url, '/');
        }

        Config::set("filesystems.disks.{$diskName}", $diskConfig);

        return Storage::disk($diskName);
    }

    /**
     * Build a runtime disk from raw driver + config (for connection tests).
     *
     * @param  array<string, mixed>  $driverConfig
     */
    public static function createFromArray(string $driver, array $driverConfig, ?string $cdnUrl = null): Filesystem
    {
        $diskName = 'test_storage_'.md5(json_encode([$driver, $driverConfig]));
        $diskConfig = self::buildDiskConfig($driver, $driverConfig);

        if ($cdnUrl) {
            $diskConfig['url'] = rtrim($cdnUrl, '/');
        }

        Config::set("filesystems.disks.{$diskName}", $diskConfig);

        return Storage::disk($diskName);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function buildDiskConfig(string $driver, array $config): array
    {
        return match ($driver) {
            'local' => self::getLocalConfig($config),
            's3' => self::getS3Config($config),
            'cloudflare_r2' => self::getCloudflareR2Config($config),
            'idrive_e2' => self::getIdriveE2Config($config),
            default => throw new InvalidArgumentException("نوع التخزين غير مدعوم: {$driver}"),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function getLocalConfig(array $config): array
    {
        return [
            'driver' => 'local',
            'root' => storage_path('app/'.($config['path'] ?? 'public')),
            // Relative so previews work regardless of APP_URL / port (e.g. 127.0.0.1:8500).
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function getS3Config(array $config): array
    {
        return [
            'driver' => 's3',
            'key' => $config['access_key_id'] ?? '',
            'secret' => $config['secret_access_key'] ?? '',
            'region' => $config['region'] ?? 'us-east-1',
            'bucket' => $config['bucket'] ?? '',
            'url' => $config['url'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => filter_var($config['use_path_style'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'throw' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function getCloudflareR2Config(array $config): array
    {
        $accountId = $config['account_id'] ?? '';

        return [
            'driver' => 's3',
            'key' => $config['access_key_id'] ?? '',
            'secret' => $config['secret_access_key'] ?? '',
            'region' => 'auto',
            'bucket' => $config['bucket'] ?? '',
            'url' => $config['public_url'] ?? ($config['url'] ?? null),
            'endpoint' => "https://{$accountId}.r2.cloudflarestorage.com",
            'use_path_style_endpoint' => true,
            'throw' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function getIdriveE2Config(array $config): array
    {
        return [
            'driver' => 's3',
            'key' => $config['access_key_id'] ?? '',
            'secret' => $config['secret_access_key'] ?? '',
            'region' => $config['region'] ?? 'us-east-1',
            'bucket' => $config['bucket'] ?? '',
            'url' => $config['url'] ?? null,
            'endpoint' => $config['endpoint'] ?? '',
            'use_path_style_endpoint' => filter_var($config['use_path_style'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'throw' => true,
        ];
    }
}
