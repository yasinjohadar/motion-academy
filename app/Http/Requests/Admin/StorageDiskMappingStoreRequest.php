<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorageDiskMappingStoreRequest extends FormRequest
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
        $disks = array_keys(config('storage.logical_disks', []));

        return [
            'disk_name' => [
                'required',
                'string',
                'max:100',
                Rule::in($disks),
                Rule::unique('storage_disk_mappings', 'disk_name'),
            ],
            'label' => ['required', 'string', 'max:255'],
            'primary_storage_id' => ['required', 'integer', 'exists:app_storage_configs,id'],
            'fallback_storage_ids' => ['nullable', 'array'],
            'fallback_storage_ids.*' => ['integer', 'exists:app_storage_configs,id', 'different:primary_storage_id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'disk_name' => 'القرص المنطقي',
            'label' => 'التسمية',
            'primary_storage_id' => 'التخزين الأساسي',
            'fallback_storage_ids' => 'التخزين الاحتياطي',
        ];
    }
}
