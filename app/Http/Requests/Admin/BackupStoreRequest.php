<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BackupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'backup_type' => ['required', 'string', Rule::in(['database', 'files', 'full'])],
            'storage_config_id' => ['required', 'integer', 'exists:app_storage_configs,id'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم النسخة',
            'backup_type' => 'نوع المحتوى',
            'storage_config_id' => 'وجهة التخزين',
            'retention_days' => 'أيام الاحتفاظ',
        ];
    }
}
