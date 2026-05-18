<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('freelancer_id');
            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('review');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('freelancer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('wwph_jobs')->onDelete('set null');

            // one review per client-freelancer-job combination
            $table->unique(['client_id', 'freelancer_id', 'job_id'], 'unique_review_per_project');

            $table->index('freelancer_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
