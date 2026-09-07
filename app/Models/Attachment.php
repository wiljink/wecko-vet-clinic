<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    public const TYPES = [
        'skin_chart' => 'Skin Chart',
        'nrsg_chart' => 'Nursing Chart',
        'serial_chart' => 'Serial Chart',
        'eye_chart' => 'Eye Chart',
        'dental_chart' => 'Dental Chart',
        'doc' => 'Document',
        'image' => 'Image',
        'video' => 'Video',
        'certificate' => 'Certificate',
        'misc' => 'Misc',
    ];

    protected $fillable = ['type', 'title', 'path', 'body', 'annotations', 'uploaded_by'];

    protected $casts = ['annotations' => 'array'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
