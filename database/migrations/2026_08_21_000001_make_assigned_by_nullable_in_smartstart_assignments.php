<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAssignedByNullableInSmartStartAssignments extends Migration
{
    public function up(): void
    {
        Schema::table('smartstart_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('smartstart_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_by')->nullable(false)->change();
        });
    }
}
