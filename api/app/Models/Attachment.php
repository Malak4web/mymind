<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = ['task_id', 'name', 'path', 'size', 'progress', 'status'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
