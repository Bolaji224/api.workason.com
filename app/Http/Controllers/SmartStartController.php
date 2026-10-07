<?php

namespace App\Http\Controllers;

use App\Mail\SmartStartSelectionMail;
use App\Models\Portfolio;
use App\Models\Resume;
use App\Models\Review;
use App\Models\SmartStartAssignment;
use App\Models\SmartStartRequest;
use App\Models\User;
use App\Models\UserSkillstamp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SmartStartController extends Controller
{
    // ── Step 1: Employer submits form → Paystack payment initiated ────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_type' => 'required|string',
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'budget_min'   => 'nullable|numeric',
            'budget_max'   => 'nullable|numeric',
            'deadline'     => 'nullable|date',
            'urgency'      => 'required|in:flexible,normal,urgent',
            'ref_links'    => 'nullable|string',
            'extra_notes'  => 'nullable|string',
            'files.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip|max:20480',
        ]);

        $fileUrls = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('smartstart/attachments', 'public');
                $fileUrls[] = asset('storage/' . $path);
            }
        }

        $reference = 'SS-' . auth()->id() . '-' . time();

        $smartStart = SmartStartRequest::create([
            ...$validated,
            'employer_id'       => auth()->id(),
            'file_urls'         => $fileUrls,
            'status'            => 'pending',
            'payment_status'    => 'unpaid',
            'payment_reference' => $reference,
        ]);

        $response = Http::withToken(config('paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email'        => auth()->user()->email,
                'amount'       => 1000000, // ₦10,000 in kobo
                'reference'    => $reference,
                'currency'     => 'NGN',
                'callback_url' => env('FRONTEND_URL') . '/smartstart/callback?reference=' . $reference,
                'metadata'     => [
                    'smartstart_id' => $smartStart->id,
                    'employer_name' => auth()->user()->name,
                    'project_type'  => $smartStart->project_type,
                ],
            ])->json();

        if (empty($response['status'])) {
            return response()->json([
                'message' => 'Could not initiate payment. Please try again.',
            ], 400);
        }

        return response()->json([
            'message'       => 'Payment initiated',
            'payment_url'   => $response['data']['authorization_url'],
            'reference'     => $reference,
            'smartstart_id' => $smartStart->id,
        ]);
    }

    // ── Step 2: Frontend calls this after Paystack redirects back ─────────────
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $smartStart = SmartStartRequest::where('payment_reference', $request->reference)
            ->where('employer_id', auth()->id())
            ->firstOrFail();

        if ($smartStart->payment_status === 'paid') {
            return response()->json([
                'message'    => 'Payment already verified.',
                'smartstart' => $smartStart,
            ]);
        }

        $response = Http::withToken(config('paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$request->reference}")
            ->json();

        if (
            isset($response['data']['status']) &&
            $response['data']['status'] === 'success' &&
            $response['data']['amount'] === 1000000
        ) {
            $smartStart->update([
                'payment_status' => 'paid',
                'paid_at'        => now(),
            ]);

            return response()->json([
                'message'    => 'Payment verified. Your SmartStart request is now active.',
                'smartstart' => $smartStart,
            ]);
        }

        return response()->json([
            'message' => 'Payment verification failed. Please contact support.',
        ], 400);
    }

    // ── List employer's SmartStart requests ───────────────────────────────────
    public function index()
    {
        $requests = SmartStartRequest::where('employer_id', auth()->id())
            ->with(['assignments'])
            ->latest()
            ->get()
            ->map(fn($r) => $this->formatSummary($r));

        return response()->json(['data' => $requests]);
    }

    // ── View single request with full Talent Pack ─────────────────────────────
    public function show($id)
    {
        $smartStart = SmartStartRequest::where('id', $id)
            ->where('employer_id', auth()->id())
            ->with(['assignments.freelancer', 'selectedFreelancer'])
            ->firstOrFail();

        $talentPack = $smartStart->assignments->map(function ($assignment) {
            $freelancer = $assignment->freelancer;
            if (!$freelancer) return null;

            $reviewStats = Review::where('freelancer_id', $freelancer->id)
                ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total_reviews')
                ->first();

            $latestReview = Review::where('freelancer_id', $freelancer->id)
                ->with('client:id,name,avatar')
                ->latest()
                ->first();

            $portfolio = Portfolio::where('user_id', $freelancer->id)
                ->select('id', 'file_url')
                ->get();

            $skillstamps = UserSkillstamp::where('user_id', $freelancer->id)
                ->select('id', 'course_name', 'score', 'earned_at')
                ->get();

            return [
                'assignment_id'     => $assignment->id,
                'assignment_status' => $assignment->status,
                'freelancer' => [
                    'id'              => $freelancer->id,
                    'name'            => $freelancer->name,
                    'avatar'          => $freelancer->avatar ? url($freelancer->avatar) : null,
                    'bio'             => $freelancer->bio,
                    'skills'          => $freelancer->skills,
                    'experience'      => $freelancer->experience,
                    'qualification'   => $freelancer->qualification,
                    'expected_salary' => $freelancer->expected_salary,
                    'country'         => $freelancer->country,
                    'city'            => $freelancer->city,
                    'avg_rating'      => (float) ($reviewStats->avg_rating ?? 0),
                    'total_reviews'   => (int) ($reviewStats->total_reviews ?? 0),
                    'latest_review'   => $latestReview ? [
                        'rating'      => $latestReview->rating,
                        'snippet'     => mb_strimwidth($latestReview->review, 0, 120, '…'),
                        'client_name' => $latestReview->client->name ?? 'Anonymous',
                        'client_avatar' => $latestReview->client->avatar
                                            ? url($latestReview->client->avatar)
                                            : null,
                    ] : null,
                    'portfolio'       => $portfolio,
                    'skillstamps'     => $skillstamps,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'data' => array_merge($this->formatSummary($smartStart), [
                'talent_pack' => $talentPack,
            ]),
        ]);
    }

    // ── Employer selects one freelancer from the Talent Pack ──────────────────
    public function selectFreelancer(Request $request, $id)
    {
        $request->validate([
            'freelancer_id' => 'required|integer|exists:users,id',
        ]);

        $smartStart = SmartStartRequest::where('id', $id)
            ->where('employer_id', auth()->id())
            ->firstOrFail();

        if ($smartStart->status !== 'matched') {
            return response()->json([
                'message' => 'You can only select a freelancer once your Talent Pack has been assigned.',
            ], 422);
        }

        $assignment = SmartStartAssignment::where('smartstart_request_id', $smartStart->id)
            ->where('freelancer_id', $request->freelancer_id)
            ->first();

        if (!$assignment) {
            return response()->json([
                'message' => 'This freelancer is not part of your Talent Pack.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            SmartStartAssignment::where('smartstart_request_id', $smartStart->id)
                ->update(['status' => 'rejected']);

            $assignment->update(['status' => 'selected']);

            $smartStart->update([
                'status'                 => 'selected',
                'selected_freelancer_id' => $request->freelancer_id,
            ]);

            DB::commit();

            // Send email to freelancer outside the transaction so a mail failure
            // never rolls back the selection that already succeeded.
            $freelancer = User::find($request->freelancer_id);
            $employer   = auth()->user();

            if ($freelancer && $employer) {
                try {
                    Mail::to($freelancer->email)
                        ->send(new SmartStartSelectionMail($freelancer, $employer, $smartStart));
                } catch (\Exception $mailEx) {
                    Log::error('SmartStart selection email failed', [
                        'freelancer_id' => $freelancer->id,
                        'error'         => $mailEx->getMessage(),
                    ]);
                }
            }

            Log::info('SmartStart freelancer selected', [
                'smartstart_id' => $smartStart->id,
                'freelancer_id' => $request->freelancer_id,
                'employer_id'   => auth()->id(),
            ]);

            return response()->json([
                'message'                => 'Freelancer selected. They will receive an email with the project details.',
                'smartstart_id'          => $smartStart->id,
                'selected_freelancer_id' => $request->freelancer_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SmartStart select failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Selection failed. Please try again.'], 500);
        }
    }

    // ── Paystack callback (Paystack redirects employer here) ──────────────────
    public function callback(Request $request)
    {
        // The frontend handles this page; just acknowledge.
        return response()->json([
            'message'   => 'Callback received. Please verify your payment.',
            'reference' => $request->query('reference'),
        ]);
    }

    public function success()
    {
        return response()->json([
            'message' => 'Payment successful. Your SmartStart request is being reviewed.',
        ]);
    }

    public function failed()
    {
        return response()->json([
            'message' => 'Payment failed or was cancelled. Please try again.',
        ], 400);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static array $statusMap = [
        'pending'   => 'pending_review',
        'matched'   => 'talent_pack_ready',
        'selected'  => 'freelancer_selected',
        'completed' => 'completed',
    ];

    private function mapStatus(string $status): string
    {
        return self::$statusMap[$status] ?? 'pending_review';
    }

    private function formatSummary(SmartStartRequest $r): array
    {
        return [
            'id'                     => $r->id,
            'title'                  => $r->title,
            'project_type'           => $r->project_type,
            'description'            => $r->description,
            'budget_min'             => $r->budget_min,
            'budget_max'             => $r->budget_max,
            'deadline'               => $r->deadline,
            'urgency'                => $r->urgency,
            'ref_links'              => $r->ref_links,
            'extra_notes'            => $r->extra_notes,
            'file_urls'              => $r->file_urls,
            'status'                 => $this->mapStatus($r->status),
            'payment_status'         => $r->payment_status,
            'paid_at'                => $r->paid_at,
            'selected_freelancer_id' => $r->selected_freelancer_id,
            'assignments_count'      => $r->assignments->count(),
            'created_at'             => $r->created_at,
        ];
    }
}
