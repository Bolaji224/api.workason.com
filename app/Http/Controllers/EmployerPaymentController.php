<?php

namespace App\Http\Controllers;

use App\Models\WalletToken;
use App\Models\EmployerPayment;
use App\Models\Milestone;
use App\Models\Wallet;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmployerPaymentController extends Controller
{
    protected $paystackService;

    // Platform rates
    const FREELANCER_COMMISSION_RATE = 0.20; 
    const EMPLOYER_FEE_RATE = 0.05; 
    const VAT_RATE = 0.075;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /** Calculated all fees and totals for a payment */
    public static function calculatePaymentBreakdown($baseAmount)
    {
        // Freelancer side (deducted from payment)
        $freelancerCommission = $baseAmount * self::FREELANCER_COMMISSION_RATE;
        // $freelancerVAT = $freelancerCommission * self::VAT_RATE;
        // $totalFreelancerDeduction = $freelancerCommission + $freelancerVAT;
        $freelancerReceives = $baseAmount - $freelancerCommission;

        // Employer side (added to payment)
        $employerFee = $baseAmount * self::EMPLOYER_FEE_RATE;
        $employerVAT = $employerFee * self::VAT_RATE;
        $totalEmployerFee = $employerFee + $employerVAT;
        $employerPaysTotal = $baseAmount + $totalEmployerFee;

        // Platform earnings (for reference)
        $platformEarnings = $freelancerCommission + $employerFee;
        $platformVAT = $employerVAT;
        $platformTotal = $platformEarnings + $platformVAT;

        return [
            'base_amount' => round($baseAmount, 2),
            
            // Freelancer breakdown
            'freelancer_commission' => round($freelancerCommission, 2),
            'freelancer_commission_vat' => 0,
            'freelancer_receives' => round($freelancerReceives, 2),
            
            // Employer breakdown
            'employer_fee' => round($employerFee, 2),
            'employer_fee_vat' => round($employerVAT, 2),
            'employer_pays_total' => round($employerPaysTotal, 2),
            
            // Platform earnings
            'platform_earnings' => round($platformEarnings, 2),
            'platform_vat' => round($platformVAT, 2),
            'platform_total' => round($platformTotal, 2),
        ];
    }

    /** Get payment breakdown (for preview before payment) */
    public function getPaymentBreakdown(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $breakdown = self::calculatePaymentBreakdown($request->amount);

        return response()->json([
            'status' => 'success',
            'data' => $breakdown,
        ]);
    }

    /**
     * Validate candidate wallet token
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'wallet_token' => 'required|string|max:255',
        ]);

        $walletToken = WalletToken::where('wallet_token', $request->wallet_token)->first();

        if (!$walletToken) {
            return response()->json([
                'status' => 'error',
                'error' => 'Invalid wallet token',
            ], 404);
        }

        $candidate = $walletToken->user;

        return response()->json([
            'status' => 'success',
            'user_id' => $walletToken->user_id,
            'candidate_name' => $candidate->first_name . ' ' . $candidate->last_name,
            'email' => $candidate->email,
        ]);
    }

    /**
     * Create escrow payment
     */
    public function fundWallet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100|max:999999999',
            'wallet_token' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:escrow,milestone',
        ]);

        $employer = auth()->user();
        $baseAmount = $request->amount;

        // Claculate breakdown
        $breakdown = self::calculatePaymentBreakdown($baseAmount);

        $reference = 'ESCROW_' . strtoupper(Str::random(12));

        $payment = EmployerPayment::create([
            'employer_id' => $employer->id,
            'candidate_id' => $request->user_id,
            'amount' => $baseAmount,
            'employer_pays_total' => $breakdown['employer_pays_total'],
            'freelancer_receives' => $breakdown['freelancer_receives'],
            'platform_commission' => $breakdown['freelancer_commission'],
            'platform_fee' => $breakdown['employer_fee'],
            'platform_vat' => $breakdown['platform_vat'],
            'type' => $request->type,
            'status' => 'pending',
            'reference' => $reference,
            'wallet_token' => $request->wallet_token,
            'payment_method' => 'paystack',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'reference' => $reference,
                'payment_id' => $payment->id,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    /**
     * Create milestone-based payment
     */
    public function createMilestones(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100|max:999999999',
            'user_id' => 'required|integer|exists:users,id',
            'wallet_token' => 'required|string|max:255',
            'steps' => 'required|array|min:1|max:10',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.percent' => 'required|numeric|min:1|max:100',
        ]);

        $totalPercent = collect($request->steps)->sum('percent');
        if ($totalPercent != 100) {
            return response()->json([
                'status' => 'error',
                'error' => 'Milestones must total exactly 100%',
            ], 422);
        }

        $employer = auth()->user();
        $baseAmount = $request->amount;
        
        // Calculate breakdown for total amount
        $breakdown = self::calculatePaymentBreakdown($baseAmount);

        $reference = 'MILESTONE_' . strtoupper(Str::random(12));

        $payment = EmployerPayment::create([
            'employer_id' => $employer->id,
            'candidate_id' => $request->user_id,
            'amount' => $baseAmount,
            'employer_pays_total' => $breakdown['employer_pays_total'],
            'freelancer_receives' => $breakdown['freelancer_receives'],
            'platform_commission' => $breakdown['freelancer_commission'],
            'platform_fee' => $breakdown['employer_fee'],
            'platform_vat' => $breakdown['platform_vat'],
            'type' => 'milestone',
            'status' => 'pending',
            'reference' => $reference,
            'wallet_token' => $request->wallet_token,
            'payment_method' => 'paystack',
        ]);

         // Create milestone records (distribute the freelancer_receives amount)
        $freelancerReceivesTotal = $breakdown['freelancer_receives'];

        foreach ($request->steps as $step) {
            $milestoneAmount = ($freelancerReceivesTotal * $step['percent']) / 100;

            Milestone::create([
                'payment_id' => $payment->id,
                'title' => $step['title'],
                'percentage' => $step['percent'],
                'amount' => round($milestoneAmount, 2),
                'status' => 'pending',
                'work_status' => 'pending',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'reference' => $reference,
                'payment_id' => $payment->id,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    /**
     * Verify payment after Paystack callback
     */
    public function verifyPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'reference' => 'required|string|max:255',
                'payment_id' => 'required|integer',
            ]);

            Log::info('Verify payment request:', $validated);

            $payment = EmployerPayment::find($validated['payment_id']);

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Payment record not found',
                ], 404);
            }

            $verification = $this->paystackService->verifyTransaction($validated['reference']);

            if (!$verification['success']) {
                return response()->json([
                    'status' => 'error',
                    'error' => $verification['error'],
                ], 400);
            }

            $paystackAmount = $verification['data']['amount'] / 100;
            $expectedAmount = (int)$payment->employer_pays_total;
            $paystackAmountInt = (int)$paystackAmount;


            if ($paystackAmountInt !== $expectedAmount) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Amount mismatch in payment verification',
                ], 400);
            }

            // Record that Paystack confirmed the money
            $payment->update([
                'paid_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified successfully. Awaiting admin approval.',
                'data' => [
                    'payment_id' => $payment->id,
                    'base_amount' => $payment->amount,
                    'total_paid' => $payment->employer_pays_total,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Error verifying payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employer payments with filters
     */
    public function getPayments(Request $request)
    {
        $employer = auth()->user();

        $query = EmployerPayment::where('employer_id', $employer->id)
            ->with('candidate', 'milestones');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $payments = $query->latest()->paginate(15);

        return response()->json($payments);
    }

    /**
     * Get all employer payments for admin
     */
    public function getAllPayments(Request $request)
    {
        $query = EmployerPayment::with('employer', 'candidate', 'milestones');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->whereHas('employer', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('candidate', function ($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $payments = $query->latest()->paginate(15);

        return response()->json($payments);
    }

    /**
     * Admin approves payment — release funds to candidate wallet
     */
    public function approvePayment(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer|exists:employer_payments,id',
        ]);

        DB::beginTransaction();

        try {
            $payment = EmployerPayment::with('candidate', 'milestones')
                ->lockForUpdate()
                ->findOrFail($request->payment_id);

            if ($payment->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Only pending payments can be approved. Current status: ' . $payment->status,
                ], 400);
            }

            $payment->update([
                'status' => 'completed',
                'paid_at' => $payment->paid_at ?? now(),
            ]);

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $payment->candidate_id],
                ['balance' => 0.00, 'currency' => 'NGN']
            );

            if (method_exists($wallet, 'credit')) {
                $wallet->credit((float) $payment->freelancer_receives, 'employer_payment', $payment->id);
            } else {
                $wallet->increment('balance', (float) $payment->freelancer_receives);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment approved and funds released to candidate wallet',
                'data' => [
                    'wallet_balance' => $wallet->fresh()->balance,
                    'amount_credited' => (float) $payment->amount,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to approve payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin rejects payment
     */
    public function rejectPayment(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer|exists:employer_payments,id',
        ]);

        $payment = EmployerPayment::findOrFail($request->payment_id);

        if ($payment->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'error' => 'Only pending payments can be rejected',
            ], 400);
        }

        $payment->update(['status' => 'failed']);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment rejected',
        ]);
    }

    /**
     * Employer approves candidate's completed work (ESCROW only)
     */
    public function approveWork(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer|exists:employer_payments,id',
        ]);

        $employer = auth()->user();

        try {
            $payment = EmployerPayment::where('employer_id', $employer->id)
                ->findOrFail($request->payment_id);

            if ($payment->type !== 'escrow') {
                return response()->json([
                    'status' => 'error',
                    'error' => 'This endpoint is only for escrow payments. Use milestone approval for milestone payments.',
                ], 400);
            }

            if ($payment->work_status === 'approved') {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Work already approved',
                ], 400);
            }

            $payment->update([
                'work_status' => 'approved',
                'work_approved_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Work approved successfully. Candidate can now withdraw funds.',
                'data' => $payment->fresh(['candidate', 'milestones']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to approve work',
            ], 500);
        }
    }

    /**
     * Employer rejects candidate's work (ESCROW only)
     */
    public function rejectWork(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer|exists:employer_payments,id',
            'reason' => 'required|string|max:500',
        ]);

        $employer = auth()->user();

        try {
            $payment = EmployerPayment::where('employer_id', $employer->id)
                ->findOrFail($request->payment_id);

            if ($payment->type !== 'escrow') {
                return response()->json([
                    'status' => 'error',
                    'error' => 'This endpoint is only for escrow payments. Use milestone rejection for milestone payments.',
                ], 400);
            }

            $payment->update([
                'work_status' => 'rejected',
                'employer_note' => $request->reason,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Work rejected',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to reject work',
            ], 500);
        }
    }

    /**
     * Employer approves a specific milestone
     */
    public function approveMilestone(Request $request)
    {
        $request->validate([
            'milestone_id' => 'required|integer|exists:milestones,id',
        ]);

        $employer = auth()->user();
        DB::beginTransaction();

        try {
            $milestone = Milestone::with('payment')->findOrFail($request->milestone_id);

            if ($milestone->payment->employer_id !== $employer->id) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'error' => 'Unauthorized'], 403);
            }

            if ($milestone->payment->status !== 'completed') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Payment must be approved by admin before you can approve milestones',
                ], 400);
            }

            if ($milestone->work_status === 'approved') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Milestone already approved',
                ], 400);
            }

            $milestone->update([
                'work_status' => 'approved',
                'work_approved_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Milestone approved successfully. Candidate can now withdraw these funds.',
                'data' => EmployerPayment::with('candidate', 'milestones')->find($milestone->payment_id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to approve milestone',
            ], 500);
        }
    }

    /**
     * Employer rejects a specific milestone
     */
    public function rejectMilestone(Request $request)
    {
        $request->validate([
            'milestone_id' => 'required|integer|exists:milestones,id',
            'reason' => 'required|string|max:500',
        ]);

        $employer = auth()->user();
        DB::beginTransaction();

        try {
            $milestone = Milestone::with('payment')->findOrFail($request->milestone_id);

            if ($milestone->payment->employer_id !== $employer->id) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'error' => 'Unauthorized'], 403);
            }

            if ($milestone->payment->status !== 'completed') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Payment must be approved by admin before you can reject milestones',
                ], 400);
            }

            if ($milestone->work_status === 'rejected') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Milestone already rejected',
                ], 400);
            }

            if ($milestone->work_status === 'approved') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Milestone already approved and cannot be rejected',
                ], 400);
            }

            $milestone->update([
                'work_status' => 'rejected',
                'employer_note' => $request->reason,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Milestone rejected',
                'data' => EmployerPayment::with('candidate', 'milestones')->find($milestone->payment_id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to reject milestone',
            ], 500);
        }
    }
}