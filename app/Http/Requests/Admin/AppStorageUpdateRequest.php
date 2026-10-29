<?php

namespace App\Http\Requests\Admin;

use App\Models\AppStorageConfig;

class AppStorageUpdateRequest extends AppStorageStoreRequest
{
    /**
     * The form never shows stored secrets, so a blank one means "keep it" — unless
     * the driver changes, where the old provider's keys are meaningless.
     */
    protected function secretRule(string $key): string
    {
        $existing = $this->route('app_storage');

        if (! $existing instanceof AppStorageConfig || $existing->driver !== $this->input('driver')) {
            return 'required';
        }

        try {
            return filled($existing->getDecryptedConfig()[$key] ?? null) ? 'nullable' : 'required';
        } catch (\RuntimeException) {
            return 'required';
        }
    }
}
