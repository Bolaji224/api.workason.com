<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Review;
use App\Models\WwphJob;
use Illuminate\Http\Request;
use App\Mail\ApplicantApprovedMail;
use App\Mail\ApplicantRejectedMail;
use Illuminate\Support\Facades\Mail;

class JobApplicationController extends Controller
{
    public function index()
    {
        $employerId = auth()->id();

        $applications = JobApplication::with(['job', 'user'])
            ->whereHas('job', fn($q) => $q->where('company_id', $employerId))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($application) use ($employerId) {
                $freelancerId = $application->user_id;

                $stats = Review::where('freelancer_id', $freelancerId)
                    ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total_reviews')
                    ->first();

                $canReview = $application->status === 'approved'
                    && !Review::where('client_id', $employerId)
                        ->where('freelancer_id', $freelancerId)
                        ->when($application->job_id, fn($q) => $q->where('job_id', $application->job_id))
                        ->exists();

                $application->review_avg   = (float) ($stats->avg_rating ?? 0);
                $application->review_count = (int) ($stats->total_reviews ?? 0);
                $application->can_review   = $canReview;

                return $application;
            });

        return response()->json([
            'status'  => 'success',
            'message' => 'Fetched job applications.',
            'data'    => $applications,
        ]);
    }

    public function show($id)
    {
        $employerId = auth()->id();

        $application = JobApplication::with(['job', 'user'])
            ->where('id', $id)
            ->whereHas('job', fn($q) => $q->where('company_id', $employerId))
            ->first();

        if (!$application) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Application not found.',
            ], 404);
        }

        $freelancerId = $application->user_id;
        $stats = Review::where('freelancer_id', $freelancerId)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total_reviews')
            ->first();

        $canReview = $application->status === 'approved'
            && !Review::where('client_id', $employerId)
                ->where('freelancer_id', $freelancerId)
                ->when($application->job_id, fn($q) => $q->where('job_id', $application->job_id))
                ->exists();

        $application->review_avg   = (float) ($stats->avg_rating ?? 0);
        $application->review_count = (int) ($stats->total_reviews ?? 0);
        $application->can_review   = $canReview;

        return response()->json([
            'status' => 'success',
            'data'   => $application,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $employerId = auth()->id();

        $application = JobApplication::where('id', $id)
            ->whereHas('job', fn($q) => $q->where('company_id', $employerId))
            ->first();

        if (!$application) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Application not found or unauthorized.',
            ], 404);
        }

        $status = $request->input('status');

        if ($status === 'approved') {
            $application->status = 'approved';
            $application->save();

            try {
                Mail::to($application->user->email)->send(
                    new ApplicantApprovedMail($application->user, $application->job)
                );
            } catch (\Throwable $e) {
                \Log::warning('Approval email failed: ' . $e->getMessage());
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Application approved.',
            ]);
        }

        if ($status === 'rejected') {
            try {
                Mail::to($application->user->email)->send(
                    new ApplicantRejectedMail($application->user, $application->job)
                );
            } catch (\Throwable $e) {
                \Log::warning('Rejection email failed: ' . $e->getMessage());
            }

            $application->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Application rejected.',
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Invalid status value provided.',
        ], 400);
    }
}
