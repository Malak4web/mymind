<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->string('category')->default('عام');
            $table->string('icon')->default('📌');
            $table->string('color')->default('from-blue-500 to-indigo-500');
            $table->string('time_of_day')->default('anytime');
            $table->string('type')->default('boolean');
            $table->integer('target_value')->default(1);
            $table->string('unit')->default('مرة');
            $table->json('frequency')->nullable();
            $table->json('logs')->nullable();
            $table->json('notes_list')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
