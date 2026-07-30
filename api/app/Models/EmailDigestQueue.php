<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDigestQueue extends Model
{
    protected $fillable = ['task_id', 'update_text'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
