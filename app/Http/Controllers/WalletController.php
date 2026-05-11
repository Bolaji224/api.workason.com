<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * Get candidate wallet info
     */
    public function getWallet($userId)
    {
        $user = auth()->user();

        // Security: User can only view their own wallet
        if ($user->id != $userId) {
            return response()->json([
                'error' => 'Unauthorized access',
            ], 403);
        }

        $wallet = Wallet::where('user_id', $userId)->first();
        $walletToken = WalletToken::where('user_id', $userId)->first();

        return response()->json([
            'balance' => $wallet ? (float)$wallet->balance : 0.00,
            'currency' => $wallet ? $wallet->currency : 'NGN',
            'wallet_token' => $walletToken ? $walletToken->wallet_token : null,
        ]);
    }

    /**
     * Generate wallet token for candidate
     */
    public function generateToken(Request $request)
    {
        $user = auth()->user();

        // Generate or regenerate token
        $token = 'WT_' . strtoupper(Str::random(16));

        WalletToken::updateOrCreate(
            ['user_id' => $user->id],
            ['wallet_token' => $token]
        );

        return response()->json([
            'status' => 'success',
            'wallet_token' => $token,
        ]);
    }

    /**
 * Get candidate transaction history (derived from payments + withdrawals)
 */
public function transactionHistory(Request $request)
{
    $user = auth()->user();

    // Credits — completed payments where admin approved
    $payments = \App\Models\EmployerPayment::where('candidate_id', $user->id)
        ->where('status', 'completed')
        ->get()
        ->map(function ($payment) {
            return [
                'id'                  => 'pay_' . $payment->id,
                'type'                => 'credit',
                'amount'              => (float) $payment->freelancer_receives,
                'source'              => 'employer_payment',
                'description'         => 'Job Payment',
                'job_amount'          => (float) $payment->amount,
                'platform_commission' => (float) $payment->platform_commission,
                'commission_rate'     => 20,
                'created_at'          => $payment->paid_at ?? $payment->created_at,
            ];
        });

    // Debits — withdrawal requests
    $withdrawals = \App\Models\Withdrawal::where('user_id', $user->id)
        ->get()
        ->map(function ($withdrawal) {
            return [
                'id'                  => 'wd_' . $withdrawal->id,
                'type'                => 'debit',
                'amount'              => (float) $withdrawal->amount,
                'source'              => 'withdrawal',
                'description'         => 'Withdrawal — ' . $withdrawal->bank_name,
                'job_amount'          => null,
                'platform_commission' => null,
                'commission_rate'     => null,
                'status'              => $withdrawal->status,
                'created_at'          => $withdrawal->created_at,
            ];
        });

    // Merge, sort newest first, paginate manually
    $perPage = 15;
    $page = (int) $request->get('page', 1);

    $all = $payments->concat($withdrawals)
        ->sortByDesc('created_at')
        ->values();

    $total = $all->count();
    $items = $all->slice(($page - 1) * $perPage, $perPage)->values();

    return response()->json([
        'data'         => $items,
        'current_page' => $page,
        'last_page'    => (int) ceil($total / $perPage),
        'total'        => $total,
    ]);
}
}