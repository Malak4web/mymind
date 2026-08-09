<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'icon',
        'color',
        'time_of_day',
        'type',
        'target_value',
        'unit',
        'frequency',
        'logs',
        'notes_list',
        'checklist'
    ];

    protected $casts = [
        'frequency' => 'array',
        'logs' => 'array',
        'notes_list' => 'array',
        'checklist' => 'array',
        'target_value' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
