<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Idea extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'images',
        'color',
        'category',
        'is_pinned',
        'idea_date',
        'sort_order',
    ];

    protected $casts = [
        'images' => 'array',
        'is_pinned' => 'boolean',
        'sort_order' => 'integer',
        'idea_date' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
