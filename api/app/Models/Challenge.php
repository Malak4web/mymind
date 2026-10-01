<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'partner_id',
        'title',
        'description',
        'category',
        'icon',
        'color',
        'start_date',
        'end_date',
        'total_days',
        'reward_title',
        'reward_icon',
        'reward_description',
        'conditions',
        'days_progress',
        'status',
    ];

    protected $casts = [
        'conditions' => 'array',
        'days_progress' => 'array',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'total_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function cheers(): HasMany
    {
        return $this->hasMany(ChallengeCheer::class)->orderBy('created_at', 'asc');
    }
}
