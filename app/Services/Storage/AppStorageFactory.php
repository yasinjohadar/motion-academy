<?php

namespace App\Services\Storage;

use App\Models\AppStorageConfig;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Builds Laravel disks for cloud accounts (S3, Cloudflare R2, IDrive e2).
 *
 * A zone ("public" / "private") puts the files under that prefix of the bucket,
 * so one bucket policy can make public/* readable while private/* stays
 * private. Without a zone (backups) the disk works at the bucket root.
 */
class AppStorageFactory
{
    public static function create(AppStorageConfig $config, ?string $zone = null): Filesystem
    {
        return self::createFromArray(
            $config->driver,
            $config->getDecryptedConfig(),
            $config->cdn_url,
            $zone,
            'app_storage_'.$config->id,
        );
    }

    /**
     * @param  array<string, mixed>  $driverConfig
     */
    public static function createFromArray(
        string $driver,
        array $driverConfig,
        ?string $cdnUrl = null,
        ?string $zone = null,
        string $prefix = 'test_storage',
    ): Filesystem {
        $diskConfig = self::buildDiskConfig($driver, $driverConfig, $zone, $cdnUrl);

        // Everything that shapes the disk is in the name: Storage::disk() caches by
        // name, so a changed driver, URL or zone must never reuse an old instance.
        $diskName = $prefix.'_'.md5(json_encode([$driver, $driverConfig, $cdnUrl, $zone]));

        Config::set("filesystems.disks.{$diskName}", $diskConfig);

        return Storage::disk($diskName);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function buildDiskConfig(string $driver, array $config, ?string $zone = null, ?string $cdnUrl = null): array
    {
        $disk = match ($driver) {
            's3' => self::getS3Config($config),
            'cloudflare_r2' => self::getCloudflareR2Config($config),
            'idrive_e2' => self::getIdriveE2Config($config),
            default => throw new InvalidArgumentException("نوع التخزين غير مدعوم: {$driver}"),
        };

        if ($zone === null) {
            return $disk;
        }

        // Accounts already holding files at the bucket root (before zones existed)
        // keep that layout instead of being silently split into public/ and private/.
        if (! filter_var($config['legacy_root'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $disk['root'] = (string) config("storage.cloud_prefixes.{$zone}", $zone);
        }

        if ($zone === 'public') {
            // The S3 adapter appends the root prefix itself (public/…).
            $publicUrl = $cdnUrl ?: ($disk['url'] ?? null);
            $disk['url'] = $publicUrl ? rtrim($publicUrl, '/') : null;

            if (filter_var($config['public_acl'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $disk['visibility'] = 'public';
            }
        } else {
            // Private files are only ever handed out as presigned URLs.
            $disk['url'] = null;
        }

        return $disk;
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
            'url' => $config['public_url'] ?? ($config['url'] ?? null),
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
            // R2 objects are only publicly reachable through the bucket's public
            // domain (r2.dev or a custom one); the API endpoint never serves them.
            'url' => $config['public_url'] ?? null,
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
            'url' => $config['public_url'] ?? ($config['url'] ?? null),
            'endpoint' => $config['endpoint'] ?? '',
            'use_path_style_endpoint' => filter_var($config['use_path_style'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'throw' => true,
        ];
    }
}
