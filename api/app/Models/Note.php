<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'folder_id', 'title', 'content'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }
}
