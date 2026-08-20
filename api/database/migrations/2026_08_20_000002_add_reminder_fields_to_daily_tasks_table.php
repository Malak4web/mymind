<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_tasks', 'reminder_at')) {
                $table->dateTime('reminder_at')->nullable()->after('due_time');
            }
            if (!Schema::hasColumn('daily_tasks', 'reminder_repeat')) {
                $table->string('reminder_repeat', 50)->default('none')->after('reminder_at');
            }
            if (!Schema::hasColumn('daily_tasks', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('reminder_repeat');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_tasks', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('daily_tasks', 'reminder_at')) $columns[] = 'reminder_at';
            if (Schema::hasColumn('daily_tasks', 'reminder_repeat')) $columns[] = 'reminder_repeat';
            if (Schema::hasColumn('daily_tasks', 'reminder_sent_at')) $columns[] = 'reminder_sent_at';
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
