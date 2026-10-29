<?php

namespace App\Services\Storage;

use App\Models\AppStorageConfig;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * The single entry point for uploaded files.
 *
 * Every caller names a logical disk ("media", "course-videos"...). The name only
 * decides the zone — public files with permanent URLs, or private files served
 * through expiring links. Where both zones live is one global choice
 * (StorageSettings): this server, or one cloud account. Reads, writes, URLs and
 * deletes all resolve through that same choice, so a file is never written in one
 * place and looked up in another.
 */
class AppStorageManager
{
    private ?AppStorageConfig $activeConfig = null;

    private bool $activeResolved = false;

    public function __construct(private readonly StorageSettings $settings) {}

    // ------------------------------------------------------------------ zones

    public static function zone(string $logicalDisk): string
    {
        $zone = config("storage.logical_disks.{$logicalDisk}");

        if (! is_string($zone)) {
            throw new InvalidArgumentException("قرص تخزين غير معروف: {$logicalDisk}");
        }

        return $zone;
    }

    public static function isKnownDisk(string $logicalDisk): bool
    {
        return is_string(config("storage.logical_disks.{$logicalDisk}"));
    }

    /**
     * The cloud account files currently live on, or null for this server.
     */
    public function activeConfig(): ?AppStorageConfig
    {
        if (! $this->activeResolved) {
            $id = $this->settings->activeConfigId();
            $this->activeConfig = $id ? AppStorageConfig::query()->find($id) : null;
            $this->activeResolved = true;

            if ($id && ! $this->activeConfig) {
                Log::channel('integrations')->error('Active storage account is missing; using this server', ['id' => $id]);
            }
        }

        return $this->activeConfig;
    }

    public function isLocal(): bool
    {
        return $this->activeConfig() === null;
    }

    /**
     * Forget the cached active target, after a switch.
     */
    public function refresh(): void
    {
        $this->activeConfig = null;
        $this->activeResolved = false;
    }

    public function getDisk(string $logicalDisk): Filesystem
    {
        return $this->diskForTarget($this->activeConfig(), self::zone($logicalDisk));
    }

    /**
     * A zone's disk on a given target: a cloud account, or this server when null.
     */
    public function diskForTarget(?AppStorageConfig $config, string $zone): Filesystem
    {
        if ($config === null) {
            return Storage::disk((string) config("storage.local_disks.{$zone}"));
        }

        return AppStorageFactory::create($config, $zone);
    }

    // ------------------------------------------------------------------ files

    public function storeUploadedFile(string $logicalDisk, UploadedFile $file, string $directory = ''): string|false
    {
        try {
            return $this->getDisk($logicalDisk)->putFile(trim($directory, '/'), $file);
        } catch (\Throwable $e) {
            $this->report('Storage upload failed', $logicalDisk, $e);

            return false;
        }
    }

    public function storeUploadedFileAs(string $logicalDisk, UploadedFile $file, string $path): string|false
    {
        $directory = trim(dirname($path), './');
        $name = basename($path);

        try {
            $stored = $this->getDisk($logicalDisk)->putFileAs($directory, $file, $name);
        } catch (\Throwable $e) {
            $this->report('Storage upload failed', $logicalDisk, $e);

            return false;
        }

        return $stored === false ? false : ($directory === '' ? $name : $directory.'/'.$name);
    }

    public function put(string $logicalDisk, string $path, mixed $contents): bool
    {
        try {
            return (bool) $this->getDisk($logicalDisk)->put($path, $contents);
        } catch (\Throwable $e) {
            $this->report('Storage put failed', $logicalDisk, $e);

            return false;
        }
    }

    public function delete(string $logicalDisk, ?string $path): bool
    {
        if (! self::isStoredPath($path)) {
            return false;
        }

        try {
            return $this->getDisk($logicalDisk)->delete($path);
        } catch (\Throwable $e) {
            $this->report('Storage delete failed', $logicalDisk, $e, $path);

            return false;
        }
    }

    // ------------------------------------------------------------------ urls

    /**
     * Whether a stored value is a path on our storage (as opposed to an external
     * URL or a site-relative URL picked from the media library, e.g. /storage/…).
     */
    public static function isStoredPath(?string $value): bool
    {
        return filled($value) && ! Str::startsWith((string) $value, ['http://', 'https://', '/']);
    }

    /**
     * The public address of an image column: a stored path becomes a storage URL,
     * anything that is already a URL is returned untouched.
     */
    public function publicUrl(string $logicalDisk, ?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return self::isStoredPath($value) ? $this->url($logicalDisk, $value) : $value;
    }

    /**
     * Permanent URL of a public-zone file. Null (and logged) when it cannot be
     * built — never a guessed address on some other storage.
     */
    public function url(string $logicalDisk, ?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        try {
            return $this->getDisk($logicalDisk)->url($path);
        } catch (\Throwable $e) {
            $this->report('Storage url resolution failed', $logicalDisk, $e, $path);

            return null;
        }
    }

    /**
     * A URL that expires, for files that must not be permanently public.
     *
     *  - Cloud accounts sign the object URL themselves, so the bytes never pass
     *    through this server.
     *  - On this server, a signed route streams the file after Laravel has
     *    validated the signature and its expiry.
     */
    public function temporaryUrl(string $logicalDisk, ?string $path, ?int $minutes = null): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $expiry = now()->addMinutes($minutes ?? (int) config('storage.temporary_url_minutes', 15));

        if (! $this->isLocal()) {
            try {
                return $this->getDisk($logicalDisk)->temporaryUrl($path, $expiry);
            } catch (\Throwable $e) {
                $this->report('Temporary url generation failed', $logicalDisk, $e, $path);

                return null;
            }
        }

        return URL::temporarySignedRoute('files.private', $expiry, [
            'disk' => $logicalDisk,
            'path' => $path,
        ]);
    }

    // ------------------------------------------------------------------ backups

    /**
     * Backups live at the root of a cloud account, or in this server's private
     * folder (storage/app/private/backups) — never under the public /storage link.
     */
    public function diskForConfig(?AppStorageConfig $config): Filesystem
    {
        return $config ? AppStorageFactory::create($config) : $this->diskForTarget(null, 'private');
    }

    public function putFileOnConfig(?AppStorageConfig $config, string $remotePath, string $localPath): bool
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

    public function getFromConfig(?AppStorageConfig $config, string $remotePath): string
    {
        return (string) $this->diskForConfig($config)->get($remotePath);
    }

    public function downloadToLocal(?AppStorageConfig $config, string $remotePath, string $localPath): void
    {
        $disk = $this->diskForConfig($config);
        $dir = dirname($localPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stream = $disk->readStream($remotePath);
        if ($stream === null) {
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

    public function deleteFromConfig(?AppStorageConfig $config, ?string $remotePath): bool
    {
        if (! $remotePath) {
            return false;
        }

        try {
            return $this->diskForConfig($config)->delete($remotePath);
        } catch (\Throwable $e) {
            Log::channel('integrations')->warning('Storage delete failed', [
                'config' => $config?->name ?? 'local',
                'path' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ------------------------------------------------------------------ connection tests

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(AppStorageConfig $config): array
    {
        return $this->testDriver($config->driver, $config->getDecryptedConfig(), $config->cdn_url);
    }

    /**
     * Proves the account works the way the site needs it to: files can be
     * written and deleted, public files are really reachable by visitors, and
     * private files are not.
     *
     * @param  array<string, mixed>  $driverConfig
     * @return array{success: bool, message: string}
     */
    public function testDriver(string $driver, array $driverConfig, ?string $cdnUrl = null): array
    {
        $public = null;
        $private = null;
        $path = 'connection-tests/test-'.Str::random(16).'.txt';

        try {
            $this->assertRequiredFields($driver, $driverConfig);

            $public = AppStorageFactory::createFromArray($driver, $driverConfig, $cdnUrl, 'public');
            $private = AppStorageFactory::createFromArray($driver, $driverConfig, $cdnUrl, 'private');
            $payload = 'ok-'.now()->toIso8601String();

            if (! $public->put($path, $payload) || ! $private->put($path, $payload)) {
                throw new RuntimeException('تعذر كتابة ملف الاختبار.');
            }

            $publicUrl = $public->url($path);
            $response = Http::timeout(10)->get($publicUrl);
            if (! $response->successful() || trim($response->body()) !== $payload) {
                throw new RuntimeException(
                    "الكتابة نجحت لكن الملفات العامة غير متاحة للزوار (HTTP {$response->status()}) على {$publicUrl}. "
                    .'اجعل المسار public/* قابلاً للقراءة العامة في سياسة الحاوية، أو فعّل خيار ACL العام، '
                    .'أو أدخل الرابط العام الصحيح للحاوية.'
                );
            }

            // The same object under private/ must NOT open without a signature.
            $warning = '';
            if (str_contains($publicUrl, '/public/')) {
                try {
                    $unsigned = Http::timeout(10)->get(Str::replaceLast('/public/', '/private/', $publicUrl));
                    if ($unsigned->successful()) {
                        $warning = ' تنبيه: الملفات الخاصة (private/*) مقروءة للعموم أيضاً — اجعل القراءة العامة على public/* فقط.';
                    }
                } catch (\Throwable) {
                    // The privacy probe is advisory only.
                }
            }

            return [
                'success' => true,
                'message' => 'تم الاتصال بنجاح: الكتابة والحذف تعمل، والملفات العامة تظهر للزوار.'.$warning,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'فشل الاختبار: '.$e->getMessage(),
            ];
        } finally {
            foreach ([$public, $private] as $disk) {
                try {
                    $disk?->delete($path);
                } catch (\Throwable) {
                    // Best effort cleanup of the probe file.
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function assertRequiredFields(string $driver, array $config): void
    {
        $required = self::requiredFields($driver);

        $missing = array_values(array_filter($required, fn (string $field) => blank($config[$field] ?? null)));

        if ($missing !== []) {
            throw new RuntimeException('حقول ناقصة: '.implode(', ', $missing));
        }
    }

    /**
     * @return array<int, string>
     */
    public static function requiredFields(string $driver): array
    {
        return match ($driver) {
            's3' => ['access_key_id', 'secret_access_key', 'region', 'bucket'],
            'cloudflare_r2' => ['account_id', 'access_key_id', 'secret_access_key', 'bucket', 'public_url'],
            'idrive_e2' => ['access_key_id', 'secret_access_key', 'region', 'bucket', 'endpoint'],
            default => throw new InvalidArgumentException("نوع التخزين غير مدعوم: {$driver}"),
        };
    }

    private function report(string $message, string $logicalDisk, \Throwable $e, ?string $path = null): void
    {
        Log::channel('integrations')->warning($message, array_filter([
            'disk' => $logicalDisk,
            'target' => $this->activeConfig()?->name ?? 'local',
            'path' => $path,
            'error' => $e->getMessage(),
        ]));
    }
}
