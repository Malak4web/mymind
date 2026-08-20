<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'due_date',
        'title',
        'category',
        'priority',
        'due_time',
        'reminder_at',
        'reminder_repeat',
        'reminder_sent_at',
        'completed'
    ];

    protected $casts = [
        'completed' => 'boolean',
        'due_date' => 'date:Y-m-d',
        'reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime'
    ];
}
