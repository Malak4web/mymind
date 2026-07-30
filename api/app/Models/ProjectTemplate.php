<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
        'statuses',
        'custom_fields',
        'task_template_ids'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'statuses' => 'array',
        'custom_fields' => 'array',
        'task_template_ids' => 'array'
    ];
}
