<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'completed_status')) {
                $table->string('completed_status')->nullable()->after('statuses');
            }
            if (!Schema::hasColumn('projects', 'separators')) {
                $table->json('separators')->nullable()->after('completed_status');
            }
            if (!Schema::hasColumn('projects', 'column_orders')) {
                $table->json('column_orders')->nullable()->after('separators');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('projects', 'column_orders')) $cols[] = 'column_orders';
            if (Schema::hasColumn('projects', 'separators')) $cols[] = 'separators';
            if (Schema::hasColumn('projects', 'completed_status')) $cols[] = 'completed_status';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
