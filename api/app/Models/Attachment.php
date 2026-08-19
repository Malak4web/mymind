<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class Attachment extends Model
{
    protected $fillable = ['task_id', 'name', 'path', 'size', 'progress', 'status'];
    protected $appends = ['url'];

    /**
     * A signed, expiring URL. Usable directly in <img src> (no bearer token),
     * but not guessable, so attachment ids can no longer be enumerated.
     */
    public function getUrlAttribute()
    {
        if (!$this->path) {
            return null;
        }

        // Sign relatively (path + query only) so the signature survives any
        // TLS-terminating proxy that rewrites scheme or host, then resolve it
        // against the current request root for the browser.
        $relative = URL::temporarySignedRoute(
            'attachments.file',
            now()->addDay(),
            ['id' => $this->id],
            absolute: false
        );

        return url($relative);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
