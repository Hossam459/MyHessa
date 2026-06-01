<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token', 512)->nullable()->index();
            }

            if (! Schema::hasColumn('users', 'fcm_token_updated_at')) {
                $table->timestamp('fcm_token_updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fcm_token')) {
                $table->dropIndex(['fcm_token']);
                $table->dropColumn('fcm_token');
            }

            if (Schema::hasColumn('users', 'fcm_token_updated_at')) {
                $table->dropColumn('fcm_token_updated_at');
            }
        });
    }
};
