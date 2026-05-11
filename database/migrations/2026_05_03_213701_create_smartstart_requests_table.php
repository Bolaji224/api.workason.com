<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmartstartRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
{
    Schema::create('smartstart_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
        $table->string('project_type');
        $table->string('title');
        $table->text('description');
        $table->unsignedBigInteger('budget_min')->nullable();
        $table->unsignedBigInteger('budget_max')->nullable();
        $table->date('deadline')->nullable();
        $table->enum('urgency', ['flexible', 'normal', 'urgent'])->default('normal');
        $table->json('file_urls')->nullable();
        $table->string('ref_links')->nullable();
        $table->text('extra_notes')->nullable();
        $table->enum('status', ['pending', 'matched', 'selected', 'completed'])->default('pending');
        $table->string('payment_reference')->nullable()->unique();
        $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
        $table->timestamp('paid_at')->nullable();
        $table->timestamps();
    });
}
}
