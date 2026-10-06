<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'app_id',
        'app_secret',
        'api_key',
        'access_token',
        'page_or_channel_id',
        'webhook_verify_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
