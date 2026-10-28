<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Storage\AppStorageManager;
use Illuminate\Http\Request;

/**
 * The image field convention of the content forms: `{field}` is an uploaded
 * file, `{field}_url` a URL picked from the media library, and
 * `remove_{field}` clears it. A replaced or removed upload is deleted.
 */
trait ResolvesImageField
{
    /**
     * Returns the new column value (a stored path, a library URL, or null to
     * clear it), or false when the current value should be left untouched.
     *
     * @param  string|null  $currentUpload  the current value when it is an uploaded path, else null
     */
    protected function resolveImageField(
        Request $request,
        string $field,
        string $disk,
        string $directory,
        ?string $currentUpload = null,
    ): string|false|null {
        $storage = app(AppStorageManager::class);

        $replacesExisting = $request->hasFile($field)
            || filled($request->input("{$field}_url"))
            || $request->boolean("remove_{$field}");

        if ($currentUpload !== null && $replacesExisting) {
            $storage->delete($disk, $currentUpload);
        }

        if ($request->hasFile($field)) {
            $path = $storage->storeUploadedFile($disk, $request->file($field), $directory);

            return $path !== false ? $path : null;
        }

        if (filled($request->input("{$field}_url"))) {
            return (string) $request->input("{$field}_url");
        }

        if ($request->boolean("remove_{$field}")) {
            return null;
        }

        return false;
    }
}
