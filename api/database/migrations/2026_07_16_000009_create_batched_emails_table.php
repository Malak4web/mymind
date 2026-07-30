<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batched_emails', function (Blueprint $table) {
            $table->id();
            $table->string('sent_at');
            $table->string('subject');
            $table->text('body');
            $table->integer('count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batched_emails');
    }
};
