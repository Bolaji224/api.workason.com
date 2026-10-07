<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('affiliate_id', 20)->unique(); // e.g. WRK-00001
            $table->string('referral_code', 20)->unique();
            $table->enum('status', ['pending', 'active', 'suspended', 'disabled'])
                  ->default('pending');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->text('notes')->nullable();              // admin-only notes

            // Denormalized counters (updated by AffiliateService)
            $table->unsignedBigInteger('total_referrals')->default(0);
            $table->unsignedBigInteger('total_conversions')->default(0);
            $table->decimal('total_commission_earned', 12, 2)->default(0);
            $table->decimal('total_commission_paid', 12, 2)->default(0);

            $table->timestamps();

            $table->index('status');
            $table->index('affiliate_id');
            $table->index('referral_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
}
