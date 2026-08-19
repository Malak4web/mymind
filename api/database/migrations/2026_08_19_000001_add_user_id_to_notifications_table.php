<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications were global: every user could read, and mark as read,
     * every other user's notifications. Scope them to an owner. See SEC-06.
     */
    public function up(): void
    {
        if (Schema::hasColumn('notifications', 'user_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Legacy rows have no owner. They stay unowned, which means they stop
        // being visible to everyone rather than leaking to the first account.
        DB::table('notifications')->whereNull('user_id')->update(['is_read' => true]);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
