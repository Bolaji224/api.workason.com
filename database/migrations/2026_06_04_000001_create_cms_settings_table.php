<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the cms_settings table.
     *
     * Each row represents one CMS module (e.g. 'homepage', 'footer').
     * The content column stores the module's editable fields as a JSON blob.
     * version tracks sequential saves for audit purposes.
     * updated_by is nullable because API-key-authenticated admin requests
     * have no associated user row.
     */
    public function up(): void
    {
        Schema::create('cms_settings', function (Blueprint $table) {
            $table->id();

            // Module identifier — 'homepage', 'employer_dashboard', etc.
            $table->string('module', 100)->unique();

            // Full JSON blob for this module's editable content fields.
            $table->json('content');

            // Incremented on every admin save for lightweight audit trail.
            $table->string('version', 20)->default('1.0.0');

            // Which admin last saved this module. Nullable because the
            // API-key auth path (X-API-Key header) has no user context.
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Index on module for fast single-module lookups.
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_settings');
    }
};
