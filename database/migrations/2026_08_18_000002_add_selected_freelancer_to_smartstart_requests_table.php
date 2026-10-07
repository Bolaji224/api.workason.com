<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSelectedFreelancerToSmartStartRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::table('smartstart_requests', function (Blueprint $table) {
            $table->foreignId('selected_freelancer_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('smartstart_requests', function (Blueprint $table) {
            $table->dropForeign(['selected_freelancer_id']);
            $table->dropColumn('selected_freelancer_id');
        });
    }
}
