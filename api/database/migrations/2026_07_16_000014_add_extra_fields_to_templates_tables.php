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
        Schema::table('project_templates', function (Blueprint $table) {
            $table->json('task_template_ids')->nullable();
        });

        Schema::table('task_templates', function (Blueprint $table) {
            $table->string('priority')->nullable();
            $table->integer('start_date_offset')->nullable();
            $table->integer('due_date_offset')->nullable();
            $table->json('attachments')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn('task_template_ids');
        });

        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn(['priority', 'start_date_offset', 'due_date_offset', 'attachments']);
        });
    }
};
