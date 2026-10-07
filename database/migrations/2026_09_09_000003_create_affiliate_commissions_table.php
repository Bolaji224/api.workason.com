<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliateCommissionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')
                  ->constrained('affiliates')
                  ->cascadeOnDelete();
            $table->foreignId('referral_id')
                  ->constrained('referrals');
            // The employer_payment that triggered this commission
            $table->foreignId('transaction_id')
                  ->nullable()
                  ->constrained('employer_payments')
                  ->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('rate', 8, 4); // rate locked at calculation time
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected', 'cancelled'])
                  ->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->foreignId('paid_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // One commission per referral per transaction — prevents duplicates
            $table->unique(['referral_id', 'transaction_id']);
            $table->index(['affiliate_id', 'status']);
            $table->index('transaction_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
}
