<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal notes were written into an undeclared store property that was
     * never persisted anywhere, so every note died on refresh.
     */
    public function up(): void
    {
        Schema::create('daily_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('note_date');
            $table->text('content');
            $table->timestamps();

            $table->index(['user_id', 'note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_notes');
    }
};
