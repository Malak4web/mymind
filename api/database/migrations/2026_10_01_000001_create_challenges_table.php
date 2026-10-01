<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('عام');
            $table->string('icon')->default('🎯');
            $table->string('color')->default('from-violet-600 to-indigo-600');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days')->default(7);
            $table->string('reward_title')->nullable();
            $table->string('reward_icon')->default('🏆');
            $table->text('reward_description')->nullable();
            $table->json('conditions')->nullable();
            $table->json('days_progress')->nullable();
            $table->string('status')->default('active'); // active, completed, abandoned
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('partner_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
