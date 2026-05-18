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

        // Fetch only admin-approved candidates
$candidates = User::where('role', 1)
->where('is_approved', true)
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
    'status'           => 'success',
    'payment_required' => false,
    'message'          => 'Fetched candidate list successfully.',
    'data'             => $candidates->map(function ($c) {
        return [
            'id'              => $c->id,
            'name'            => $c->name,
            'first_name'      => $c->first_name,
            'last_name'       => $c->last_name,
            'email'           => $c->email,
            'avatar'          => $c->avatar ? url($c->avatar) : null,
            'bio'             => $c->bio,
            'skills'          => $c->skills,
            'education'       => $c->education,
            'experience'      => $c->experience,
            'expected_salary' => $c->expected_salary,
            'city'            => $c->city,
            'country'         => $c->country,
            'cv'              => $c->cv ? url($c->cv) : null,
            'smartcv'         => $c->smartcv ? url($c->smartcv) : null,
            'completed_jobs'  => 0,
        ];
    }),
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
