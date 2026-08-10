<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = ['task_id', 'name', 'path', 'size', 'progress', 'status'];
    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        if (!$this->path) return null;
        return url("/api/attachments/{$this->id}/file");
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
