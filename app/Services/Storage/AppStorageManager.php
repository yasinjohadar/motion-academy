<?php

namespace App\Services\Storage;

use App\Models\AppStorageConfig;
use App\Models\StorageDiskMapping;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class AppStorageManager
{
    public function diskForConfig(AppStorageConfig $config): Filesystem
    {
        return AppStorageFactory::create($config);
    }

    public function putFileOnConfig(AppStorageConfig $config, string $remotePath, string $localPath): bool
    {
        $disk = $this->diskForConfig($config);
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('تعذر فتح الملف المحلي للرفع.');
        }

        try {
            return (bool) $disk->put($remotePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function getFromConfig(AppStorageConfig $config, string $remotePath): string
    {
        return $this->diskForConfig($config)->get($remotePath);
    }

    public function downloadToLocal(AppStorageConfig $config, string $remotePath, string $localPath): void
    {
        $disk = $this->diskForConfig($config);
        $dir = dirname($localPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stream = $disk->readStream($remotePath);
        if ($stream === null) {
            // Fallback for drivers without streams
            file_put_contents($localPath, $disk->get($remotePath));

            return;
        }

        $out = fopen($localPath, 'wb');
        if ($out === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('تعذر إنشاء ملف التنزيل المحلي.');
        }

        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            fclose($out);
        }
    }

    public function deleteFromConfig(AppStorageConfig $config, ?string $remotePath): bool
    {
        if (! $remotePath) {
            return false;
        }

        try {
            return $this->diskForConfig($config)->delete($remotePath);
        } catch (\Throwable $e) {
            Log::channel('integrations')->warning('Storage delete failed', [
                'config' => $config->name,
                'path' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getDisk(string $logicalDisk): Filesystem
    {

        $mapping = StorageDiskMapping::query()
            ->where('disk_name', $logicalDisk)
            ->where('is_active', true)
            ->with('primaryStorage')
            ->first();

        if ($mapping?->primaryStorage?->is_active) {
            try {
                return AppStorageFactory::create($mapping->primaryStorage);
            } catch (\Throwable $e) {
                Log::channel('integrations')->warning('Primary storage unavailable', [
                    'disk' => $logicalDisk,
                    'storage' => $mapping->primaryStorage?->name,
                    'error' => $e->getMessage(),
                ]);

                foreach ($mapping->getFallbackStorages() as $fallback) {
                    try {
                        return AppStorageFactory::create($fallback);
                    } catch (\Throwable $fallbackError) {
                        Log::channel('integrations')->warning('Fallback storage unavailable', [
                            'disk' => $logicalDisk,
                            'storage' => $fallback->name,
                            'error' => $fallbackError->getMessage(),
                        ]);
                    }
                }
            }
        }

        return Storage::disk(config('storage.fallback_disk', 'public'));
    }

    public function storeUploadedFile(string $logicalDisk, UploadedFile $file, string $directory = ''): string|false
    {
        $disk = $this->getDisk($logicalDisk);
        $directory = trim($directory, '/');

        return $directory === ''
            ? $disk->putFile('', $file)
            : $disk->putFile($directory, $file);
    }

    public function storeUploadedFileAs(string $logicalDisk, UploadedFile $file, string $path): string|false
    {
        $disk = $this->getDisk($logicalDisk);
        $directory = trim(dirname($path), '.');
        $name = basename($path);

        $stored = $directory === '' || $directory === '/'
            ? $disk->putFileAs('', $file, $name)
            : $disk->putFileAs($directory, $file, $name);

        return $stored === false ? false : ($directory === '' || $directory === '/' ? $name : trim($directory, '/').'/'.$name);
    }

    public function put(string $logicalDisk, string $path, mixed $contents): bool
    {
        $mapping = StorageDiskMapping::query()
            ->where('disk_name', $logicalDisk)
            ->where('is_active', true)
            ->with('primaryStorage')
            ->first();

        if (! $mapping?->primaryStorage) {
            return Storage::disk(config('storage.fallback_disk', 'public'))->put($path, $contents);
        }

        $candidates = collect([$mapping->primaryStorage])->merge($mapping->getFallbackStorages());

        foreach ($candidates as $config) {
            if (! $config->is_active) {
                continue;
            }

            try {
                if (AppStorageFactory::create($config)->put($path, $contents)) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::channel('integrations')->warning('Storage put failed', [
                    'storage' => $config->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Storage::disk(config('storage.fallback_disk', 'public'))->put($path, $contents);
    }

    public function delete(string $logicalDisk, ?string $path): bool
    {
        if (! $path) {
            return false;
        }

        try {
            return $this->getDisk($logicalDisk)->delete($path);
        } catch (\Throwable $e) {
            Log::channel('integrations')->warning('Storage delete failed', [
                'disk' => $logicalDisk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function url(string $logicalDisk, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = $this->getDisk($logicalDisk);

            return $disk->url($path);
        } catch (\Throwable $e) {
            Log::channel('integrations')->warning('Storage url resolution failed', [
                'disk' => $logicalDisk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            try {
                return Storage::disk(config('storage.fallback_disk', 'public'))->url($path);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(AppStorageConfig $config): array
    {
        return $this->testDriver($config->driver, $config->getDecryptedConfig(), $config->cdn_url);
    }

    /**
     * @param  array<string, mixed>  $driverConfig
     * @return array{success: bool, message: string}
     */
    public function testDriver(string $driver, array $driverConfig, ?string $cdnUrl = null): array
    {
        try {
            $this->assertRequiredFields($driver, $driverConfig);

            $disk = AppStorageFactory::createFromArray($driver, $driverConfig, $cdnUrl);
            $path = 'connection-tests/test-'.uniqid('', true).'.txt';
            $payload = 'ok-'.now()->toIso8601String();

            if (! $disk->put($path, $payload)) {
                throw new RuntimeException('تعذر كتابة ملف الاختبار.');
            }

            $exists = $disk->exists($path);
            $disk->delete($path);

            if (! $exists) {
                throw new RuntimeException('تم الرفع لكن الملف غير موجود.');
            }

            return [
                'success' => true,
                'message' => 'تم الاتصال بنجاح وكتابة/حذف ملف اختبار.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'فشل الاتصال: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function assertRequiredFields(string $driver, array $config): void
    {
        $required = match ($driver) {
            'local' => [],
            's3' => ['access_key_id', 'secret_access_key', 'region', 'bucket'],
            'cloudflare_r2' => ['account_id', 'access_key_id', 'secret_access_key', 'bucket'],
            'idrive_e2' => ['access_key_id', 'secret_access_key', 'region', 'bucket', 'endpoint'],
            default => throw new InvalidArgumentException("نوع التخزين غير مدعوم: {$driver}"),
        };

        $missing = [];
        foreach ($required as $field) {
            if (blank($config[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('حقول ناقصة: '.implode(', ', $missing));
        }
    }
}
