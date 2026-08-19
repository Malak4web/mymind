<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'start_date',
        'deadline',
        'priority'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    protected static function booted()
    {
        static::created(function ($task) {
            EmailDigestQueue::create([
                'task_id' => $task->id,
                'update_text' => 'تم إنشاء المهمة "' . $task->title . '".'
            ]);
        });

        static::updated(function ($task) {
            $changes = [];
            if ($task->wasChanged('status')) {
                $changes[] = 'تغيرت الحالة إلى "' . $task->status . '"';
            }
            if ($task->wasChanged('title')) {
                $changes[] = 'تم تحديث العنوان إلى "' . $task->title . '"';
            }
            if ($task->wasChanged('deadline')) {
                $changes[] = 'تم تحديث موعد الاستحقاق إلى "' . ($task->deadline ?? 'غير محدد') . '"';
            }
            
            if (!empty($changes)) {
                EmailDigestQueue::create([
                    'task_id' => $task->id,
                    'update_text' => 'تم تحديث المهمة "' . $task->title . '": ' . implode('، ', $changes) . '.'
                ]);
            }
        });
    }
}
