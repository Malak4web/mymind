<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'priority',
        'due_time',
        'completed'
    ];

    protected $casts = [
        'completed' => 'boolean'
    ];
}
