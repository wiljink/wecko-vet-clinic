<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'name', 'type', 'channel', 'subject', 'body',
        'patient_reminder_type_id', 'sequence', 'is_active',
    ];

    protected $casts = ['sequence' => 'integer', 'is_active' => 'boolean'];

    public function reminderType(): BelongsTo
    {
        return $this->belongsTo(PatientReminderType::class, 'patient_reminder_type_id');
    }

    /** Render subject + body against a merge-field array ({{ dot.notation }}). */
    public function render(array $data): array
    {
        $replace = fn (string $text): string => preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            fn ($m) => (string) data_get($data, $m[1], ''),
            $text,
        );

        return [
            'subject' => $replace((string) $this->subject),
            'body' => $replace($this->body),
        ];
    }
}
