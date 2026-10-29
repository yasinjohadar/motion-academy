<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Storage\AppStorageManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The image field convention of the content forms: `{field}` is an uploaded
 * file, `{field}_url` a URL picked from the media library, and
 * `remove_{field}` clears it. A replaced or removed upload is deleted — but only
 * once its replacement is safely stored.
 */
trait ResolvesImageField
{
    /**
     * Returns the new column value (a stored path, a library URL, or null to
     * clear it), or false when the current value should be left untouched.
     *
     * @param  string|null  $currentUpload  the current value when it is an uploaded path, else null
     *
     * @throws ValidationException when the upload fails (the current image is kept)
     */
    protected function resolveImageField(
        Request $request,
        string $field,
        string $disk,
        string $directory,
        ?string $currentUpload = null,
    ): string|false|null {
        $storage = app(AppStorageManager::class);

        if ($request->hasFile($field)) {
            $next = $storage->storeUploadedFile($disk, $request->file($field), $directory);

            if ($next === false) {
                throw ValidationException::withMessages([$field => 'تعذّر رفع الصورة. لم يتم تغيير الصورة الحالية.']);
            }
        } elseif (filled($request->input("{$field}_url"))) {
            $next = (string) $request->input("{$field}_url");
        } elseif ($request->boolean("remove_{$field}")) {
            $next = null;
        } else {
            return false;
        }

        if ($currentUpload !== null && $currentUpload !== $next) {
            $storage->delete($disk, $currentUpload);
        }

        return $next;
    }
}
