<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminAffiliateController extends Controller
{
    // ── GET /api/v1/admin/affiliate/overview ─────────────────────────────────

    public function overview()
    {
        $commissions = AffiliateCommission::query();

        return okResponse('Overview loaded.', [
            'affiliates' => [
                'total'     => Affiliate::count(),
                'active'    => Affiliate::where('status', 'active')->count(),
                'pending'   => Affiliate::where('status', 'pending')->count(),
                'suspended' => Affiliate::where('status', 'suspended')->count(),
                'disabled'  => Affiliate::where('status', 'disabled')->count(),
            ],
            'referrals' => [
                'total'     => Referral::count(),
                'clicked'   => Referral::where('status', 'clicked')->count(),
                'registered'=> Referral::where('status', 'registered')->count(),
                'converted' => Referral::where('status', 'converted')->count(),
                'rejected'  => Referral::where('status', 'rejected')->count(),
            ],
            'commissions' => [
                'total_amount'    => (float) (clone $commissions)->sum('amount'),
                'pending_amount'  => (float) (clone $commissions)->where('status', 'pending')->sum('amount'),
                'approved_amount' => (float) (clone $commissions)->where('status', 'approved')->sum('amount'),
                'paid_amount'     => (float) (clone $commissions)->where('status', 'paid')->sum('amount'),
                'rejected_amount' => (float) (clone $commissions)->where('status', 'rejected')->sum('amount'),
                'pending_count'   => (clone $commissions)->where('status', 'pending')->count(),
                'approved_count'  => (clone $commissions)->where('status', 'approved')->count(),
                'paid_count'      => (clone $commissions)->where('status', 'paid')->count(),
            ],
        ]);
    }

    // ── GET /api/v1/admin/affiliate ──────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Affiliate::with('user:id,name,first_name,last_name,email,avatar,created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('affiliate_id', 'like', "%{$search}%")
                  ->orWhere('referral_code', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) =>
                      $u->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                  );
            });
        }

        $affiliates = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $affiliates,
        ]);
    }

    // ── GET /api/v1/admin/affiliate/{id} ─────────────────────────────────────

    public function show(int $id)
    {
        $affiliate = Affiliate::with([
            'user:id,name,first_name,last_name,email,avatar,created_at',
        ])->findOrFail($id);

        $stats = [
            'total_referrals'     => $affiliate->total_referrals,
            'total_conversions'   => $affiliate->total_conversions,
            'commission_earned'   => (float) $affiliate->total_commission_earned,
            'commission_paid'     => (float) $affiliate->total_commission_paid,
            'pending_commission'  => (float) $affiliate->commissions()->where('status', 'pending')->sum('amount'),
            'approved_commission' => (float) $affiliate->commissions()->where('status', 'approved')->sum('amount'),
        ];

        return okResponse('Affiliate loaded.', [
            'affiliate' => $affiliate,
            'stats'     => $stats,
            'referral_link' => $affiliate->referral_link,
        ]);
    }

    // ── GET /api/v1/admin/affiliate/{id}/referrals ────────────────────────────

    public function referrals(Request $request, int $id)
    {
        $affiliate = Affiliate::findOrFail($id);

        $query = $affiliate->referrals()
            ->with('referredUser:id,name,first_name,last_name,email,created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->latest()->paginate(20),
        ]);
    }

    // ── GET /api/v1/admin/affiliate/{id}/commissions ──────────────────────────

    public function commissions(Request $request, int $id)
    {
        $affiliate = Affiliate::findOrFail($id);

        $query = $affiliate->commissions()->with('referral', 'transaction');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->latest()->paginate(20),
        ]);
    }

    // ── POST /api/v1/admin/affiliate/{id}/suspend ─────────────────────────────

    public function suspend(int $id)
    {
        $affiliate = Affiliate::findOrFail($id);

        if ($affiliate->status === 'suspended') {
            return errorResponse('Affiliate is already suspended.', [], 422);
        }

        $affiliate->update(['status' => 'suspended']);

        Log::info('Affiliate suspended', ['affiliate_id' => $affiliate->id, 'by' => auth()->id()]);

        return okResponse('Affiliate suspended successfully.');
    }

    // ── POST /api/v1/admin/affiliate/{id}/activate ────────────────────────────

    public function activate(int $id)
    {
        $affiliate = Affiliate::findOrFail($id);

        if ($affiliate->status === 'active') {
            return errorResponse('Affiliate is already active.', [], 422);
        }

        $affiliate->update(['status' => 'active']);

        Log::info('Affiliate activated', ['affiliate_id' => $affiliate->id, 'by' => auth()->id()]);

        return okResponse('Affiliate reactivated successfully.');
    }

    // ── POST /api/v1/admin/affiliate/commissions/{id}/approve ─────────────────

    public function approveCommission(int $id)
    {
        $commission = AffiliateCommission::with('affiliate')->findOrFail($id);

        if ($commission->status !== 'pending') {
            return errorResponse('Only pending commissions can be approved. Current status: ' . $commission->status, [], 422);
        }

        $commission->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        Log::info('Affiliate commission approved', [
            'commission_id' => $commission->id,
            'affiliate_id'  => $commission->affiliate_id,
            'amount'        => $commission->amount,
            'by'            => auth()->id(),
        ]);

        return okResponse('Commission approved.', ['commission' => $commission->fresh()]);
    }

    // ── POST /api/v1/admin/affiliate/commissions/{id}/reject ──────────────────

    public function rejectCommission(Request $request, int $id)
    {
        $commission = AffiliateCommission::with('affiliate')->findOrFail($id);

        if (!in_array($commission->status, ['pending', 'approved'])) {
            return errorResponse('Commission cannot be rejected at this stage.', [], 422);
        }

        $commission->update([
            'status' => 'rejected',
            'notes'  => $request->input('notes'),
        ]);

        // Reverse the earned total if it was previously counted
        if ($commission->status === 'approved') {
            $commission->affiliate->decrement('total_commission_earned', $commission->amount);
        }

        Log::info('Affiliate commission rejected', [
            'commission_id' => $commission->id,
            'by'            => auth()->id(),
        ]);

        return okResponse('Commission rejected.');
    }

    // ── POST /api/v1/admin/affiliate/commissions/{id}/mark-paid ──────────────

    public function markPaid(Request $request, int $id)
    {
        $commission = AffiliateCommission::with('affiliate')->findOrFail($id);

        if ($commission->status !== 'approved') {
            return errorResponse('Only approved commissions can be marked as paid. Current status: ' . $commission->status, [], 422);
        }

        DB::transaction(function () use ($commission, $request) {
            $commission->update([
                'status'  => 'paid',
                'paid_at' => now(),
                'paid_by' => auth()->id(),
                'notes'   => $request->input('notes', $commission->notes),
            ]);

            $commission->affiliate->increment('total_commission_paid', $commission->amount);
        });

        Log::info('Affiliate commission marked paid', [
            'commission_id' => $commission->id,
            'affiliate_id'  => $commission->affiliate_id,
            'amount'        => $commission->amount,
            'by'            => auth()->id(),
        ]);

        return okResponse('Commission marked as paid.', ['commission' => $commission->fresh()]);
    }

    // ── GET /api/v1/admin/affiliate/referrals (all referrals) ────────────────

    public function allReferrals(Request $request)
    {
        $query = Referral::with([
            'affiliate:id,affiliate_id,referral_code,user_id',
            'affiliate.user:id,name,first_name,last_name,email',
            'referredUser:id,name,first_name,last_name,email,created_at',
            'commission:id,referral_id,amount,currency,status',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('referral_code', 'like', "%{$search}%")
                  ->orWhere('tracking_token', 'like', "%{$search}%")
                  ->orWhereHas('referredUser', fn($u) =>
                      $u->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                  )
                  ->orWhereHas('affiliate', fn($a) =>
                      $a->where('affiliate_id', 'like', "%{$search}%")
                  );
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->latest()->paginate(20),
        ]);
    }

    // ── GET /api/v1/admin/affiliate/referrals/{id} ────────────────────────────

    public function showReferral(int $id)
    {
        $referral = Referral::with([
            'affiliate:id,affiliate_id,referral_code,status,user_id',
            'affiliate.user:id,name,first_name,last_name,email',
            'referredUser:id,name,first_name,last_name,email,role,created_at',
            'commission',
            'commission.transaction:id,amount,employer_pays_total,freelancer_receives,status,paid_at',
        ])->findOrFail($id);

        return okResponse('Referral loaded.', $referral);
    }

    // ── PATCH /api/v1/admin/affiliate/{id}/status ─────────────────────────────

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:active,pending,suspended,disabled',
        ]);

        $affiliate = Affiliate::findOrFail($id);
        $old       = $affiliate->status;
        $new       = $request->status;

        if ($old === $new) {
            return errorResponse("Affiliate is already {$new}.", [], 422);
        }

        $affiliate->update(['status' => $new]);

        Log::info('Affiliate status updated', [
            'affiliate_id' => $affiliate->id,
            'from'         => $old,
            'to'           => $new,
            'by'           => auth()->id(),
        ]);

        return okResponse("Affiliate status changed to {$new}.", [
            'affiliate_id' => $affiliate->affiliate_id,
            'status'       => $affiliate->status,
        ]);
    }

    // ── GET /api/v1/admin/affiliate-payments ─────────────────────────────────

    public function affiliatePayments(Request $request)
    {
        $query = AffiliateCommission::with([
            'affiliate:id,affiliate_id,referral_code,user_id',
            'affiliate.user:id,name,first_name,last_name,email',
            'referral:id,affiliate_id,referred_user_id,status',
        ])->where('status', 'paid');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('affiliate', fn($q) =>
                $q->where('affiliate_id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) =>
                      $u->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                  )
            );
        }

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->to);
        }

        $payments = $query->latest('paid_at')->paginate(20);

        $totals = [
            'total_paid'     => (float) AffiliateCommission::where('status', 'paid')->sum('amount'),
            'total_approved' => (float) AffiliateCommission::where('status', 'approved')->sum('amount'),
            'total_pending'  => (float) AffiliateCommission::where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'status'  => 'success',
            'totals'  => $totals,
            'data'    => $payments,
        ]);
    }

    // ── GET /api/v1/admin/affiliate/commissions (all commissions) ─────────────

    public function allCommissions(Request $request)
    {
        $query = AffiliateCommission::with([
            'affiliate.user:id,name,first_name,last_name,email',
            'referral',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('affiliate', fn($q) =>
                $q->where('affiliate_id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) =>
                      $u->where('email', 'like', "%{$search}%")
                  )
            );
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->latest()->paginate(20),
        ]);
    }
}
