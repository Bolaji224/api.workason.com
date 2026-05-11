<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    public function approve($id)
    {
        $user = User::findOrFail($id);
        $user->is_approved = true;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'User approved']);
    }

    public function suspend($id)
    {
        $user = User::findOrFail($id);
        $user->is_suspended = true;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'User suspended']);
    }

    public function changePassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|confirmed|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Password updated']);
    }

    public function logoutAll($id)
    {
        $user = User::findOrFail($id);
        // Example: remove all tokens for user (if using Laravel Sanctum)
        $user->tokens()->delete();

        return response()->json(['status' => 'success', 'message' => 'Logged out from all devices']);
    }
    
    public function destroy($id)
{
    $user = User::findOrFail($id);
    $user->delete();
    return response()->json(['status' => 'success', 'message' => 'User deleted']);
}
}
