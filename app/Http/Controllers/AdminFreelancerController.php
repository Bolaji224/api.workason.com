<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminFreelancerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::where('role', 1);

            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $query->where(function ($q) use ($s) {
                    $q->where('name', 'like', $s)
                      ->orWhere('first_name', 'like', $s)
                      ->orWhere('last_name', 'like', $s)
                      ->orWhere('email', 'like', $s)
                      ->orWhere('experience', 'like', $s)
                      ->orWhere('skills', 'like', $s);
                });
            }

            if ($request->filled('status')) {
                $query->where('is_approved', $request->status === 'approved');
            }

            $freelancers = $query->select(
                'id', 'name', 'first_name', 'last_name', 'email', 'avatar',
                'bio', 'experience', 'skills', 'city', 'country', 'is_approved', 'created_at'
            )->latest()->get()->map(function ($u) {
                return [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'first_name'  => $u->first_name,
                    'last_name'   => $u->last_name,
                    'email'       => $u->email,
                    'avatar'      => $u->avatar ? url($u->avatar) : null,
                    'bio'         => $u->bio,
                    'experience'  => $u->experience,
                    'skills'      => $u->skills,
                    'city'        => $u->city,
                    'country'     => $u->country,
                    'is_approved' => (bool) $u->is_approved,
                    'joined_at'   => $u->created_at ? $u->created_at->format('M d, Y') : null,
                ];
            });

            $total    = User::where('role', 1)->count();
            $approved = User::where('role', 1)->where('is_approved', true)->count();
            $pending  = $total - $approved;

            return response()->json([
                'status' => 'success',
                'data'   => $freelancers,
                'stats'  => compact('total', 'approved', 'pending'),
            ]);

        } catch (\Exception $e) {
            Log::error('AdminFreelancerController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to fetch freelancers.',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $u = User::where('role', 1)->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'first_name'  => $u->first_name,
                    'last_name'   => $u->last_name,
                    'email'       => $u->email,
                    'avatar'      => $u->avatar ? url($u->avatar) : null,
                    'bio'         => $u->bio,
                    'experience'  => $u->experience,
                    'skills'      => $u->skills,
                    'city'        => $u->city,
                    'country'     => $u->country,
                    'is_approved' => (bool) $u->is_approved,
                    'joined_at'   => $u->created_at ? $u->created_at->format('M d, Y') : null,
                    'cv'          => $u->cv ? url($u->cv) : null,
                    'smartcv'     => $u->smartcv ? url($u->smartcv) : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('AdminFreelancerController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Freelancer not found.',
            ], 404);
        }
    }

    public function approve($id)
    {
        try {
            $u = User::where('role', 1)->findOrFail($id);
            $u->update(['is_approved' => true]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Freelancer approved successfully.',
                'data'    => ['id' => $u->id, 'is_approved' => true],
            ]);

        } catch (\Exception $e) {
            Log::error('AdminFreelancerController@approve: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to approve freelancer.',
            ], 500);
        }
    }

    public function revoke($id)
    {
        try {
            $u = User::where('role', 1)->findOrFail($id);
            $u->update(['is_approved' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Approval revoked.',
                'data'    => ['id' => $u->id, 'is_approved' => false],
            ]);

        } catch (\Exception $e) {
            Log::error('AdminFreelancerController@revoke: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to revoke approval.',
            ], 500);
        }
    }
}
