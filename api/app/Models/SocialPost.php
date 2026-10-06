<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'media_urls',
        'platforms',
        'account_ids',
        'status',
        'scheduled_at',
        'published_at',
        'platform_post_ids',
        'error_message',
        'metrics',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'platforms' => 'array',
        'account_ids' => 'array',
        'platform_post_ids' => 'array',
        'metrics' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
