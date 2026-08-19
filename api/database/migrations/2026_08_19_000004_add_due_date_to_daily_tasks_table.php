<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Daily" tasks had no date at all, so the day navigator above them did
     * nothing and the same list appeared under every date forever.
     */
    public function up(): void
    {
        Schema::table('daily_tasks', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('user_id')->index();
        });

        // Existing rows belong to the day they were created.
        DB::statement("UPDATE daily_tasks SET due_date = date(created_at) WHERE due_date IS NULL");
    }

    public function down(): void
    {
        Schema::table('daily_tasks', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
