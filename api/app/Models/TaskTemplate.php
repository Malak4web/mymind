<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
        'title',
        'description',
        'status',
        'custom_fields_values',
        'priority',
        'start_date_offset',
        'due_date_offset',
        'attachments'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'custom_fields_values' => 'array',
        'start_date_offset' => 'integer',
        'due_date_offset' => 'integer',
        'attachments' => 'array'
    ];
}
