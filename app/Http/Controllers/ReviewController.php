<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * GET /v1/reviews/freelancer/{id}
     * Returns all reviews + stats for a freelancer. Accessible without auth.
     */
    public function getFreelancerReviews($freelancerId)
    {
        $reviews = Review::with(['client', 'job'])
            ->where('freelancer_id', $freelancerId)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalReviews = $reviews->count();
        $avgRating = $totalReviews > 0 ? round($reviews->avg('rating'), 1) : 0;

        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = $reviews->where('rating', $star)->count();
            $breakdown[$star] = [
                'count'      => $count,
                'percentage' => $totalReviews > 0 ? round(($count / $totalReviews) * 100) : 0,
            ];
        }

        $formatted = $reviews->map(function ($r) {
            return [
                'id'           => $r->id,
                'rating'       => $r->rating,
                'review'       => $r->review,
                'created_at'   => $r->created_at,
                'client_name'  => $r->client->name ?? 'Anonymous',
                'client_avatar'=> $r->client->avatar ?? null,
                'job_title'    => $r->job->title ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'reviews'        => $formatted,
                'average_rating' => $avgRating,
                'total_reviews'  => $totalReviews,
                'breakdown'      => $breakdown,
            ],
        ]);
    }

    /**
     * GET /v1/reviews/freelancer/{id}/summary
     * Lightweight summary used for compact badges. No auth required.
     */
    public function getFreelancerSummary($freelancerId)
    {
        $stats = Review::where('freelancer_id', $freelancerId)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total_reviews')
            ->first();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'average_rating' => (float) ($stats->avg_rating ?? 0),
                'total_reviews'  => (int) ($stats->total_reviews ?? 0),
            ],
        ]);
    }

    /**
     * POST /v1/employer/reviews
     * Employer submits a review for an approved freelancer.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'freelancer_id' => 'required|integer|exists:users,id',
            'job_id'        => 'nullable|integer|exists:wwph_jobs,id',
            'rating'        => 'required|integer|min:1|max:5',
            'review'        => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $clientId     = auth()->id();
        $freelancerId = (int) $request->freelancer_id;
        $jobId        = $request->job_id ? (int) $request->job_id : null;

        if ($clientId === $freelancerId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You cannot review yourself.',
            ], 403);
        }

        $hasApprovedApplication = JobApplication::where('user_id', $freelancerId)
            ->whereHas('job', fn($q) => $q->where('company_id', $clientId))
            ->where('status', 'approved')
            ->exists();

        if (!$hasApprovedApplication) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You can only review freelancers you have approved.',
            ], 403);
        }

        $alreadyReviewed = Review::where('client_id', $clientId)
            ->where('freelancer_id', $freelancerId)
            ->when($jobId, fn($q) => $q->where('job_id', $jobId))
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have already reviewed this freelancer for this project.',
            ], 409);
        }

        $review = Review::create([
            'client_id'     => $clientId,
            'freelancer_id' => $freelancerId,
            'job_id'        => $jobId,
            'rating'        => $request->rating,
            'review'        => $request->review,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Review submitted successfully.',
            'data'    => $review,
        ], 201);
    }

    /**
     * GET /v1/employer/reviews/can-review/{freelancerId}
     * Returns whether the authenticated employer can still leave a review.
     */
    public function canReview($freelancerId)
    {
        $clientId = auth()->id();

        $hasApproved = JobApplication::where('user_id', $freelancerId)
            ->whereHas('job', fn($q) => $q->where('company_id', $clientId))
            ->where('status', 'approved')
            ->exists();

        $hasReviewed = Review::where('client_id', $clientId)
            ->where('freelancer_id', $freelancerId)
            ->exists();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'can_review'   => $hasApproved && !$hasReviewed,
                'has_approved' => $hasApproved,
                'has_reviewed' => $hasReviewed,
            ],
        ]);
    }
}
