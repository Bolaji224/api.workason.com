<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferredByToUsersTable extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which affiliate referred this user (null = organic)
            $table->foreignId('referred_by')
                  ->nullable()
                  ->after('admin_token_expires_at')
                  ->constrained('affiliates')
                  ->nullOnDelete();

            $table->index('referred_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn('referred_by');
        });
    }
}
