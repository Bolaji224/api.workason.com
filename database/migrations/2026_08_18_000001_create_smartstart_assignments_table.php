<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmartStartAssignmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('smartstart_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smartstart_request_id')->constrained('smartstart_requests')->onDelete('cascade');
            $table->foreignId('freelancer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['assigned', 'selected', 'rejected'])->default('assigned');
            $table->timestamps();

            $table->unique(['smartstart_request_id', 'freelancer_id'], 'unique_request_freelancer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smartstart_assignments');
    }
}
