<?php

namespace App\Services;

use App\Mail\AffiliateWelcomeMail;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\EmployerPayment;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AffiliateService
{
    // ── Registration ──────────────────────────────────────────────────────────

    public function register(User $user): Affiliate
    {
        if (Affiliate::where('user_id', $user->id)->exists()) {
            throw new \RuntimeException('User is already an affiliate.');
        }

        return DB::transaction(function () use ($user) {
            $affiliate = Affiliate::create([
                'user_id'      => $user->id,
                'affiliate_id' => $this->generateUniqueAffiliateId(),
                'referral_code'=> $this->generateUniqueReferralCode(),
                'status'       => 'active',
            ]);

            try {
                Mail::to($user->email)->send(new AffiliateWelcomeMail($user, $affiliate));
            } catch (\Throwable $e) {
                Log::error('Affiliate welcome email failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            return $affiliate;
        });
    }

    // ── ID / Code Generation ──────────────────────────────────────────────────

    public function generateUniqueAffiliateId(): string
    {
        $prefix  = config('affiliate.id_prefix', 'WRK');
        $padding = config('affiliate.id_padding', 5);

        // Use the highest existing numeric suffix + 1, padded to avoid gaps
        $last = Affiliate::orderByDesc('id')->lockForUpdate()->value('affiliate_id');
        if ($last) {
            $num = (int) substr($last, strlen($prefix) + 1) + 1;
        } else {
            $num = 1;
        }

        do {
            $id = $prefix . '-' . str_pad($num, $padding, '0', STR_PAD_LEFT);
            $num++;
        } while (Affiliate::where('affiliate_id', $id)->exists());

        return $id;
    }

    public function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Affiliate::where('referral_code', $code)->exists());

        return $code;
    }

    // ── Referral Link ─────────────────────────────────────────────────────────

    public function getReferralLink(Affiliate $affiliate): string
    {
        return $affiliate->referral_link;
    }

    // ── Click Tracking ────────────────────────────────────────────────────────

    /**
     * Record that someone clicked a referral link.
     * Returns the referral with its tracking_token for the frontend to persist.
     */
    public function trackClick(string $referralCode, array $context = []): ?Referral
    {
        $affiliate = Affiliate::where('referral_code', $referralCode)
            ->where('status', 'active')
            ->first();

        if (!$affiliate) {
            return null;
        }

        $ipHash = isset($context['ip'])
            ? hash('sha256', $context['ip'])
            : null;

        $referral = Referral::create([
            'affiliate_id'   => $affiliate->id,
            'referral_code'  => $referralCode,
            'tracking_token' => (string) Str::uuid(),
            'source'         => $context['source']       ?? null,
            'landing_page'   => $context['landing_page'] ?? null,
            'ip_hash'        => $ipHash,
            'status'         => 'clicked',
        ]);

        $affiliate->increment('total_referrals');

        return $referral;
    }

    // ── Registration Attribution ──────────────────────────────────────────────

    /**
     * Called during user registration when a referral_token is provided.
     * Links the new user to the referral and sets their referred_by.
     */
    public function attributeRegistration(User $user, string $trackingToken): void
    {
        $referral = Referral::where('tracking_token', $trackingToken)
            ->where('status', 'clicked')
            ->where('referred_user_id', null)
            ->first();

        if (!$referral) {
            return; // token not found, expired, or already used — silently skip
        }

        // Attribution window check
        $days = config('affiliate.attribution_days', 30);
        if ($referral->created_at->diffInDays(now()) > $days) {
            return;
        }

        // Self-referral prevention
        if ($referral->affiliate->user_id === $user->id) {
            Log::warning('Self-referral attempt blocked', [
                'user_id'      => $user->id,
                'affiliate_id' => $referral->affiliate_id,
            ]);
            return;
        }

        DB::transaction(function () use ($user, $referral) {
            $referral->update([
                'referred_user_id' => $user->id,
                'status'           => 'registered',
                'registered_at'    => now(),
            ]);

            $user->update(['referred_by' => $referral->affiliate_id]);
        });
    }

    // ── Conversion / Commission ───────────────────────────────────────────────

    /**
     * Called when an EmployerPayment is approved by admin.
     * Finds the referred employer, checks eligibility, creates commission.
     */
    public function processConversion(EmployerPayment $payment): void
    {
        // Only process completed payments
        if ($payment->status !== 'completed') {
            return;
        }

        $employer = User::find($payment->employer_id);
        if (!$employer || !$employer->referred_by) {
            return; // employer was not referred
        }

        $affiliate = Affiliate::find($employer->referred_by);
        if (!$affiliate || !$affiliate->isActive()) {
            return; // affiliate is inactive or deleted
        }

        // Self-referral safeguard (belt-and-suspenders)
        if ($affiliate->user_id === $employer->id) {
            Log::warning('Self-referral commission blocked', [
                'employer_id'  => $employer->id,
                'affiliate_id' => $affiliate->id,
            ]);
            return;
        }

        // Find the referral record that linked this employer to the affiliate
        $referral = Referral::where('affiliate_id', $affiliate->id)
            ->where('referred_user_id', $employer->id)
            ->whereIn('status', ['registered', 'qualified'])
            ->first();

        if (!$referral) {
            return;
        }

        // Duplicate prevention — only one commission per referral per transaction
        $alreadyExists = AffiliateCommission::where('referral_id', $referral->id)
            ->where('transaction_id', $payment->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        DB::transaction(function () use ($affiliate, $referral, $payment) {
            $commissionAmount = $this->calculateCommission($payment);
            $rate             = $this->getActiveRate();

            AffiliateCommission::create([
                'affiliate_id'   => $affiliate->id,
                'referral_id'    => $referral->id,
                'transaction_id' => $payment->id,
                'amount'         => $commissionAmount,
                'currency'       => 'NGN',
                'rate'           => $rate,
                'status'         => 'pending',
            ]);

            $referral->update([
                'status'       => 'converted',
                'converted_at' => now(),
            ]);

            $affiliate->increment('total_conversions');
            $affiliate->increment('total_commission_earned', $commissionAmount);

            Log::info('Affiliate commission created', [
                'affiliate_id'   => $affiliate->id,
                'referral_id'    => $referral->id,
                'transaction_id' => $payment->id,
                'amount'         => $commissionAmount,
            ]);
        });
    }

    // ── Commission Calculation ────────────────────────────────────────────────

    public function calculateCommission(EmployerPayment $payment): float
    {
        $type = config('affiliate.commission_type', 'percentage');

        if ($type === 'fixed') {
            return (float) config('affiliate.fixed_amount', 500);
        }

        // Percentage of platform earnings (platform_commission + platform_fee)
        $platformEarnings = (float) $payment->platform_commission
                          + (float) $payment->platform_fee;

        $rate = $this->getActiveRate();

        return round($platformEarnings * $rate, 2);
    }

    public function getActiveRate(): float
    {
        return (float) config('affiliate.commission_rate', 0.10);
    }

    // ── Dashboard Statistics ──────────────────────────────────────────────────

    public function getDashboardStats(Affiliate $affiliate): array
    {
        $commissions = $affiliate->commissions();

        return [
            'affiliate_id'        => $affiliate->affiliate_id,
            'referral_code'       => $affiliate->referral_code,
            'referral_link'       => $affiliate->referral_link,
            'status'              => $affiliate->status,
            'member_since'        => $affiliate->created_at->toDateString(),
            'statistics' => [
                'total_referrals'      => (int) $affiliate->total_referrals,
                'converted_customers'  => (int) $affiliate->total_conversions,
                'commission_earned'    => (float) $affiliate->total_commission_earned,
                'commission_paid'      => (float) $affiliate->total_commission_paid,
                'pending_commission'   => (float) $commissions->where('status', 'pending')->sum('amount'),
                'approved_commission'  => (float) $commissions->where('status', 'approved')->sum('amount'),
            ],
        ];
    }
}
