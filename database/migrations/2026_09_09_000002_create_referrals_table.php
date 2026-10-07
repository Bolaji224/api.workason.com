<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferralsTable extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')
                  ->constrained('affiliates')
                  ->cascadeOnDelete();
            // Null until the visitor actually registers
            $table->foreignId('referred_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('referral_code', 20);           // denormalised for query speed
            $table->string('tracking_token', 64)->unique(); // UUID returned to frontend
            $table->string('source', 100)->nullable();      // utm_source
            $table->string('landing_page', 500)->nullable();
            $table->string('ip_hash', 64)->nullable();      // SHA-256 of IP — no raw PII
            $table->enum('status', [
                'clicked',
                'registered',
                'qualified',
                'converted',
                'rejected',
                'cancelled',
            ])->default('clicked');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
            $table->index('referral_code');
            $table->index('referred_user_id');
            $table->index('tracking_token');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
}
