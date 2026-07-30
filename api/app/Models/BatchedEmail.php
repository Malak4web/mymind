<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchedEmail extends Model
{
    protected $fillable = ['sent_at', 'subject', 'body', 'count'];
}
