<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Payment;

class BrowseCandidatesController extends Controller
{
    public function index(Request $request)
    {
        $employer = auth()->user();

        //  Ensure user is an employer (role = 2)
        if ($employer->role != 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized – only employers can access this route.'
            ], 403);
        }

        //  Check if employer has a valid payment
        $payment = Payment::where('employer_id', $employer->id)
            ->where('status', 'success')
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'status' => 'success',
                'payment_required' => true,
                'message' => 'Payment required to view candidates.'
            ]);
        }

        // Fetch all candidate users (role = 1)
$candidates = User::where('role', 1)
->select(
    'id',
    'name',
    'email',
    'first_name',
    'last_name',
    'country',
    'city',
    'avatar',
    'skills',
    'education',
    'experience',
    'expected_salary',
    'cv',
    'bio',
    'smartcv'
)
->get();

return response()->json([
'status' => 'success',
'payment_required' => false,
'message' => 'Fetched candidate list successfully.',
'data' => $candidates,
]);
    }

    public function storePayment(Request $request)
    {
        $employer = auth()->user();

        $validated = $request->validate([
            'reference' => 'required|string',
            'amount' => 'required|numeric',
            'status' => 'required|string',
        ]);

        //  Save using employer_id
        $payment = Payment::create([
            'employer_id' => $employer->id,
            'reference' => $validated['reference'],
            'amount' => $validated['amount'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded successfully.',
            'data' => $payment,
        ]);
    }
}
