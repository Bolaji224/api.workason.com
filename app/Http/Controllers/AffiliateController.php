<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Referral;
use App\Services\AffiliateService;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function __construct(private AffiliateService $affiliateService) {}

    // ── POST /api/v1/affiliate/register ──────────────────────────────────────

    public function register(Request $request)
    {
        $user = auth()->user();

        if (Affiliate::where('user_id', $user->id)->exists()) {
            return errorResponse('You are already registered as an affiliate.', [], 422);
        }

        $request->validate([
            'terms_accepted' => 'required|accepted',
        ]);

        try {
            $affiliate = $this->affiliateService->register($user);

            $affiliate->update(['terms_accepted_at' => now()]);

            return okResponse('Welcome to the Workason Affiliate Programme!', [
                'affiliate_id'  => $affiliate->affiliate_id,
                'referral_code' => $affiliate->referral_code,
                'referral_link' => $affiliate->referral_link,
                'status'        => $affiliate->status,
            ]);
        } catch (\RuntimeException $e) {
            return errorResponse($e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            \Log::error('Affiliate registration failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return errorResponse('Registration failed. Please try again.', [], 500);
        }
    }

    // ── GET /api/v1/affiliate/dashboard ──────────────────────────────────────

    public function dashboard()
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        return okResponse('Dashboard loaded.', $this->affiliateService->getDashboardStats($affiliate));
    }

    // ── GET /api/v1/affiliate/profile ─────────────────────────────────────────

    public function profile()
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        $user = auth()->user();

        return okResponse('Profile loaded.', [
            'affiliate_id'      => $affiliate->affiliate_id,
            'referral_code'     => $affiliate->referral_code,
            'referral_link'     => $affiliate->referral_link,
            'status'            => $affiliate->status,
            'terms_accepted_at' => $affiliate->terms_accepted_at,
            'member_since'      => $affiliate->created_at->toDateString(),
            'user' => [
                'name'   => $user->name ?? ($user->first_name . ' ' . $user->last_name),
                'email'  => $user->email,
                'avatar' => $user->avatar ? url($user->avatar) : null,
            ],
        ]);
    }

    // ── GET /api/v1/affiliate/referral-link ───────────────────────────────────

    public function referralLink()
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        return okResponse('Referral link retrieved.', [
            'referral_link' => $affiliate->referral_link,
            'referral_code' => $affiliate->referral_code,
        ]);
    }

    // ── GET /api/v1/affiliate/referrals ───────────────────────────────────────

    public function referrals(Request $request)
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        $referrals = $affiliate->referrals()
            ->with('referredUser:id,name,first_name,last_name,email,created_at')
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $referrals,
        ]);
    }

    // ── GET /api/v1/affiliate/commissions ─────────────────────────────────────

    public function commissions(Request $request)
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        $query = $affiliate->commissions()->with('referral');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $commissions = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $commissions,
        ]);
    }

    // ── GET /api/v1/affiliate/payments ───────────────────────────────────────

    public function payments(Request $request)
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        $payments = $affiliate->commissions()
            ->where('status', 'paid')
            ->with('referral')
            ->latest('paid_at')
            ->paginate(20);

        $summary = [
            'total_paid'    => (float) $affiliate->total_commission_paid,
            'total_earned'  => (float) $affiliate->total_commission_earned,
            'total_pending' => (float) $affiliate->commissions()->where('status', 'pending')->sum('amount'),
            'total_approved'=> (float) $affiliate->commissions()->where('status', 'approved')->sum('amount'),
        ];

        return response()->json([
            'status'  => 'success',
            'summary' => $summary,
            'data'    => $payments,
        ]);
    }

    // ── GET /api/v1/affiliate/terms ───────────────────────────────────────────

    public function terms()
    {
        $affiliate = $this->getAuthAffiliate();

        return okResponse('Terms loaded.', [
            'terms_accepted'    => $affiliate ? (bool) $affiliate->terms_accepted_at : false,
            'terms_accepted_at' => $affiliate?->terms_accepted_at,
            'terms' => [
                'title'   => 'Workason Affiliate Programme Terms',
                'version' => '1.0',
                'points'  => [
                    'You earn commission when a user you referred completes their first approved payment on Workason.',
                    'Commission is calculated as a percentage of the platform\'s earnings — not the full transaction amount.',
                    'Self-referrals are not permitted. You cannot refer yourself.',
                    'Commissions are reviewed and approved by the Workason admin team before payout.',
                    'Workason reserves the right to reject commissions arising from fraudulent or abusive referral activity.',
                    'Your affiliate account may be suspended if abuse is detected.',
                    'Commission rates and terms may be updated with prior notice.',
                ],
            ],
        ]);
    }

    // ── POST /api/v1/affiliate/accept-terms ──────────────────────────────────

    public function acceptTerms()
    {
        $affiliate = $this->getAuthAffiliate();
        if (!$affiliate) {
            return errorResponse('You are not registered as an affiliate.', [], 403);
        }

        if ($affiliate->terms_accepted_at) {
            return okResponse('Terms already accepted.', [
                'terms_accepted_at' => $affiliate->terms_accepted_at,
            ]);
        }

        $affiliate->update(['terms_accepted_at' => now()]);

        return okResponse('Terms accepted.', ['terms_accepted_at' => $affiliate->terms_accepted_at]);
    }

    // ── GET /api/v1/referral/click/{code}  (public — no auth) ────────────────

    public function trackClick(Request $request, string $code)
    {
        $context = [
            'ip'           => $request->ip(),
            'source'       => $request->query('utm_source'),
            'landing_page' => $request->query('landing_page', $request->header('Referer')),
        ];

        $referral = $this->affiliateService->trackClick($code, $context);

        if (!$referral) {
            return errorResponse('Invalid or inactive referral code.', [], 404);
        }

        return okResponse('Referral tracked.', [
            'tracking_token' => $referral->tracking_token,
            'expires_in_days'=> config('affiliate.attribution_days', 30),
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function getAuthAffiliate(): ?Affiliate
    {
        return Affiliate::where('user_id', auth()->id())->first();
    }
}
