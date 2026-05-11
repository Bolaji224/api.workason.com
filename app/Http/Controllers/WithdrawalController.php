<?php 

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    /**
     * Get candidate's withdrawal history
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return response()->json($withdrawals);
    }

    /**
     * Submit withdrawal request
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:500|max:10000000',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|min:10|max:10',
            'account_name' => 'required|string|max:255',
            'swift_code'     => 'nullable|string|max:11',
            'iban'           => 'nullable|string|max:34',
        ]);

        if (!$request->account_number && !$request->iban) {
    return response()->json([
        'status' => 'error',
        'error'  => 'Please provide either an account number or IBAN.',
    ], 422);
}

        $user = auth()->user();

        DB::beginTransaction();

        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Wallet not found. Please contact support.',
                ], 404);
            }

            if ($wallet->balance < $request->amount) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'error' => 'Insufficient wallet balance.',
                    'data' => [
                        'requested' => (float)$request->amount,
                        'available' => (float)$wallet->balance,
                    ],
                ], 400);
            }

            $approvedEscrowPayments = \App\Models\EmployerPayment::where('candidate_id', $user->id)
                ->where('status', 'completed')
                ->where('type', 'escrow')
                ->where('work_status', 'approved')
                ->get();

            $escrowBalance = $approvedEscrowPayments->sum('freelancer_receives');

            $approvedMilestones = \App\Models\Milestone::whereHas('payment', function ($query) use ($user) {
                    $query->where('candidate_id', $user->id)
                        ->where('status', 'completed');
                })
                ->where('work_status', 'approved')
                ->get();

            $milestoneBalance = $approvedMilestones->sum('amount');

            $approvedBalance = $escrowBalance + $milestoneBalance;

            $withdrawnFromApproved = Withdrawal::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');

            $availableForWithdrawal = $approvedBalance - $withdrawnFromApproved;

            Log::info('Withdrawal validation', [
                'user_id' => $user->id,
                'wallet_balance' => $wallet->balance,
                'escrow_approved' => $escrowBalance,
                'milestones_approved' => $milestoneBalance,
                'total_approved_balance' => $approvedBalance,
                'withdrawn_amount' => $withdrawnFromApproved,
                'available_for_withdrawal' => $availableForWithdrawal,
                'requested_amount' => $request->amount,
            ]);

            if ($availableForWithdrawal < $request->amount) {
                DB::rollBack();

                $pendingEscrow = \App\Models\EmployerPayment::where('candidate_id', $user->id)
                    ->where('status', 'completed')
                    ->where('type', 'escrow')
                    ->where('work_status', 'pending')
->count();

                $pendingMilestones = \App\Models\Milestone::whereHas('payment', function ($query) use ($user) {
                        $query->where('candidate_id', $user->id)
                            ->where('status', 'completed');
                    })
                    ->where('work_status', 'pending')
                    ->count();

                $totalPending = $pendingEscrow + $pendingMilestones;

                return response()->json([
                    'status' => 'error',
                    'error' => 'You can only withdraw from employer-approved work.',
                    'data' => [
                        'requested_amount' => (float)$request->amount,
                        'available_for_withdrawal' => (float)$availableForWithdrawal,
                        'escrow_approved' => (float)$escrowBalance,
                        'milestones_approved' => (float)$milestoneBalance,
                        'total_approved' => (float)$approvedBalance,
                        'already_withdrawn' => (float)$withdrawnFromApproved,
                        'pending_approvals' => $totalPending,
                        'message' => $totalPending > 0
                            ? "You have {$totalPending} item(s) pending employer approval."
                            : 'No approved payments or milestones available for withdrawal.',
                    ],
                ], 400);
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $request->amount,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'status' => 'pending',
                'reference' => 'WD_' . strtoupper(Str::random(12)),
            ]);

            $wallet->decrement('balance', $request->amount);

            if (class_exists(\App\Models\WalletTransaction::class)) {
                \App\Models\WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $request->amount,
                    'balance_after' => $wallet->fresh()->balance,
                    'source' => 'withdrawal',
                    'source_id' => $withdrawal->id,
                    'description' => 'Withdrawal request: ' . $withdrawal->reference,
                ]);
            }

            DB::commit();

            Log::info('Withdrawal request created successfully', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'amount' => $request->amount,
                'reference' => $withdrawal->reference,
                'new_wallet_balance' => $wallet->fresh()->balance,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Withdrawal request submitted successfully. Pending admin approval.',
                'data' => [
                    'reference' => $withdrawal->reference,
                    'amount' => (float)$withdrawal->amount,
                    'new_balance' => (float)$wallet->fresh()->balance,
                    'bank_name' => $withdrawal->bank_name,
                    'account_number' => $withdrawal->account_number,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating withdrawal', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'Failed to process withdrawal request. Please try again.',
            ], 500);
        }
    }

    /**
     * Get candidate's available withdrawal balance
     */
    public function getAvailableBalance(Request $request)
    {
        $user =
auth()->user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json([
                'wallet_balance' => 0,
                'approved_balance' => 0,
                'available_for_withdrawal' => 0,
            ]);
        }

        $approvedEscrowBalance = \App\Models\EmployerPayment::where('candidate_id', $user->id)
            ->where('status', 'completed')
            ->where('type', 'escrow')
            ->where('work_status', 'approved')
            ->sum('freelancer_receives');

        $approvedMilestoneBalance = \App\Models\Milestone::whereHas('payment', function ($query) use ($user) {
                $query->where('candidate_id', $user->id)
                    ->where('status', 'completed');
            })
            ->where('work_status', 'approved')
            ->sum('amount');

        $approvedBalance = $approvedEscrowBalance + $approvedMilestoneBalance;

        $withdrawnAmount = Withdrawal::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        $availableForWithdrawal = $approvedBalance - $withdrawnAmount;

        return response()->json([
            'wallet_balance' => (float)$wallet->balance,
            'escrow_approved' => (float)$approvedEscrowBalance,
            'milestones_approved' => (float)$approvedMilestoneBalance,
            'total_approved' => (float)$approvedBalance,
            'withdrawn_or_pending' => (float)$withdrawnAmount,
            'available_for_withdrawal' => (float)$availableForWithdrawal,
        ]);
    }

    /**
     * Cancel pending withdrawal
     */
    public function cancel($id)
    {
        $user = auth()->user();

        DB::beginTransaction();

        try {
            $withdrawal = Withdrawal::where('user_id', $user->id)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Withdrawal not found',
                ], 404);
            }

            if ($withdrawal->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Can only cancel pending withdrawals',
                ], 400);
            }

            $wallet = Wallet::find($withdrawal->wallet_id);
            $wallet->increment('balance', $withdrawal->amount);

            $withdrawal->update([
                'status' => 'rejected',
                'admin_note' => 'Cancelled by user'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Withdrawal cancelled. Funds returned to wallet.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'error' => 'Failed to cancel withdrawal',
            ], 500);
        }
    }
}