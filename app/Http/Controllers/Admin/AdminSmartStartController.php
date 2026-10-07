<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Review;
use App\Models\SmartStartAssignment;
use App\Models\SmartStartRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminSmartStartController extends Controller
{
    /**
     * GET /admin/smartstart
     * List all SmartStart requests with optional status filter.
     */
    public function index(Request $request)
    {
        $query = SmartStartRequest::with(['employer'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('project_type', 'like', "%{$search}%")
                  ->orWhereHas('employer', fn($q2) => $q2
                      ->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                  );
            });
        }

        $requests = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $requests,
        ]);
    }

    /**
     * GET /admin/smartstart/{id}
     * View a single request with its employer info and already-assigned freelancers.
     */
    public function show($id)
    {
        $smartStart = SmartStartRequest::with([
            'employer',
            'assignments.freelancer',
            'selectedFreelancer',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatRequest($smartStart),
        ]);
    }

    /**
     * POST /admin/smartstart/{id}/mark-paid
     * Manually mark a SmartStart request as paid (manual override / bank transfer).
     */
    public function markPaid($id)
    {
        $smartStart = SmartStartRequest::findOrFail($id);

        if ($smartStart->payment_status === 'paid') {
            return response()->json([
                'status'  => 'success',
                'message' => 'Already marked as paid.',
                'data'    => $this->formatRequest($smartStart->load(['assignments.freelancer', 'employer', 'selectedFreelancer'])),
            ]);
        }

        $smartStart->update([
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        Log::info('SmartStart manually marked as paid by admin', [
            'smartstart_id' => $smartStart->id,
            'admin_id'      => auth()->id(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment marked as paid.',
            'data'    => $this->formatRequest($smartStart->load(['assignments.freelancer', 'employer', 'selectedFreelancer'])),
        ]);
    }

    /**
     * POST /admin/smartstart/{id}/assign
     * Assign 3–5 freelancers to a SmartStart request → status becomes "matched".
     *
     * Body: { "freelancer_ids": [1, 2, 3] }
     */
    public function assign(Request $request, $id)
    {
        $request->validate([
            'freelancer_ids'   => 'required|array|min:3|max:5',
            'freelancer_ids.*' => 'required|integer|exists:users,id',
        ]);

        $smartStart = SmartStartRequest::findOrFail($id);

        if ($smartStart->payment_status !== 'paid') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cannot assign freelancers to an unpaid SmartStart request.',
            ], 422);
        }

        // Verify all IDs are candidates (role == 1)
        $freelancerCount = User::whereIn('id', $request->freelancer_ids)
            ->where('role', 1)
            ->count();

        if ($freelancerCount !== count($request->freelancer_ids)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'All assigned users must be candidates (role 1).',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Remove previous assignments before re-assigning
            SmartStartAssignment::where('smartstart_request_id', $smartStart->id)->delete();

            $adminId = auth()->id();
            $rows = array_map(fn($fid) => [
                'smartstart_request_id' => $smartStart->id,
                'freelancer_id'         => $fid,
                'assigned_by'           => $adminId,
                'status'                => 'assigned',
                'created_at'            => now(),
                'updated_at'            => now(),
            ], $request->freelancer_ids);

            SmartStartAssignment::insert($rows);

            $smartStart->update([
                'status'                => 'matched',
                'selected_freelancer_id'=> null,
            ]);

            DB::commit();

            Log::info('SmartStart freelancers assigned', [
                'smartstart_id'  => $smartStart->id,
                'freelancer_ids' => $request->freelancer_ids,
                'admin_id'       => $adminId,
            ]);

            $smartStart->load(['assignments.freelancer', 'employer']);

            return response()->json([
                'status'  => 'success',
                'message' => count($request->freelancer_ids) . ' freelancers assigned. Employer can now view their Talent Pack.',
                'data'    => $this->formatRequest($smartStart),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SmartStart assignment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Assignment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatRequest(SmartStartRequest $r): array
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
            'status'                 => $r->status,
            'payment_status'         => $r->payment_status,
            'paid_at'                => $r->paid_at,
            'payment_reference'      => $r->payment_reference,
            'created_at'             => $r->created_at,
            'employer'               => $r->employer ? [
                'id'     => $r->employer->id,
                'name'   => $r->employer->name,
                'email'  => $r->employer->email,
                'avatar' => $r->employer->avatar ? url($r->employer->avatar) : null,
            ] : null,
            'selected_freelancer_id' => $r->selected_freelancer_id,
            'assignments'            => $r->assignments->map(fn($a) => [
                'id'         => $a->id,
                'status'     => $a->status,
                'freelancer' => $a->freelancer ? [
                    'id'     => $a->freelancer->id,
                    'name'   => $a->freelancer->name,
                    'email'  => $a->freelancer->email,
                    'avatar' => $a->freelancer->avatar ? url($a->freelancer->avatar) : null,
                ] : null,
            ]),
        ];
    }
}
