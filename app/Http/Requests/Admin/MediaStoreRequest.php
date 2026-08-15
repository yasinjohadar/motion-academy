<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class MediaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('media.max_upload_kb', 10240);
        $mimes = config('media.allowed_mimes', []);

        return [
            'file' => [
                'required',
                'file',
                File::types(config('media.allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']))
                    ->max($maxKb),
                function (string $attribute, mixed $value, \Closure $fail) use ($mimes): void {
                    if (! $value || ! method_exists($value, 'getMimeType')) {
                        return;
                    }
                    $mime = $value->getMimeType();
                    if ($mime && $mimes !== [] && ! in_array($mime, $mimes, true)) {
                        $fail('نوع الملف غير مسموح.');
                    }
                },
            ],
            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
