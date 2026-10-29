<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ChoosesBackupDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BackupScheduleStoreRequest extends FormRequest
{
    use ChoosesBackupDestination;

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
            'name' => ['required', 'string', 'max:255'],
            'backup_type' => ['required', 'string', Rule::in(['database', 'files', 'full'])],
            'storage_config_id' => $this->backupDestinationRules(),
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'time' => ['required', 'date_format:H:i'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6', 'required_if:frequency,weekly'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28', 'required_if:frequency,monthly'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم الجدولة',
            'backup_type' => 'نوع المحتوى',
            'storage_config_id' => 'وجهة التخزين',
            'frequency' => 'التكرار',
            'time' => 'الوقت',
            'day_of_week' => 'يوم الأسبوع',
            'day_of_month' => 'يوم الشهر',
            'retention_days' => 'أيام الاحتفاظ',
        ];
    }
}
