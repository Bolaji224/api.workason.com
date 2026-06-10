<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminEmployerController extends Controller
{
    public function index()
    {
        $employers = User::where('role', 2)
            ->select('id', 'name', 'email', 'avatar', 'is_suspended')
            ->get()
            ->map(function ($u) {
                return [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'email'        => $u->email,
                    'avatar'       => $u->avatar ? url($u->avatar) : null,
                    'is_suspended' => (bool) $u->is_suspended,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $employers,
        ]);
    }

    public function suspend($id)
    {
        try {
            $u = User::where('role', 2)->findOrFail($id);
            $u->update(['is_suspended' => true]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Employer suspended.',
                'data'    => ['id' => $u->id, 'is_suspended' => true],
            ]);

        } catch (\Exception $e) {
            Log::error('AdminEmployerController@suspend: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to suspend employer.',
            ], 500);
        }
    }

    public function unsuspend($id)
    {
        try {
            $u = User::where('role', 2)->findOrFail($id);
            $u->update(['is_suspended' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Employer unsuspended.',
                'data'    => ['id' => $u->id, 'is_suspended' => false],
            ]);

        } catch (\Exception $e) {
            Log::error('AdminEmployerController@unsuspend: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to unsuspend employer.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $u = User::where('role', 2)->findOrFail($id);
            $u->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Employer deleted.',
            ]);

        } catch (\Exception $e) {
            Log::error('AdminEmployerController@destroy: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete employer.',
            ], 500);
        }
    }
}
