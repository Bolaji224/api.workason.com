<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class BackfillAffiliates extends Command
{
    protected $signature   = 'affiliates:backfill';
    protected $description = 'Create affiliate records for all users who do not have one';

    public function handle(): void
    {
        Mail::fake();

        $service = new AffiliateService();
        $users   = User::all();
        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if (Affiliate::where('user_id', $user->id)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $service->register($user);
                $created++;
                $this->line("✓ User {$user->id} ({$user->email})");
            } catch (\Throwable $e) {
                $this->warn("✗ User {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("\nDone. Created: {$created} | Skipped (already had one): {$skipped}");
    }
}
