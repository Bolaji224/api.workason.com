<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Country;
use App\Models\SocialMedia;
use App\Models\States;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::all();
            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        // ── Avatar upload ──────────────────────────────────────────────────
        if ($request->hasFile('file')) {
            try {
                $request->validate([
                    'file' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                ]);

                // Delete the old avatar if it was stored locally
                if ($user->avatar && strpos($user->avatar, '/uploads/avatars/') === 0) {
                    $oldFile = public_path($user->avatar);
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                $uploadDir = public_path('uploads/avatars');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext      = strtolower($request->file('file')->getClientOriginalExtension());
                $fileName = time() . '_' . uniqid() . '.' . $ext;
                $request->file('file')->move($uploadDir, $fileName);

                $user->avatar = '/uploads/avatars/' . $fileName;

                Log::info('Avatar saved: ' . $user->avatar . ' for user ' . $user->id);

            } catch (\Exception $e) {
                Log::error('Avatar upload failed for user ' . $user->id . ': ' . $e->getMessage());
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Image upload failed: ' . $e->getMessage(),
                ], 422);
            }
        }

        // ── CV upload ──────────────────────────────────────────────────────
        if ($request->hasFile('cv')) {
            try {
                $cvDir = public_path('uploads/cv');
                if (!is_dir($cvDir)) {
                    mkdir($cvDir, 0755, true);
                }
                $cvFile = time() . '_' . $request->file('cv')->getClientOriginalName();
                $request->file('cv')->move($cvDir, $cvFile);
                $user->cv = '/uploads/cv/' . $cvFile;
            } catch (\Exception $e) {
                Log::error('CV upload failed: ' . $e->getMessage());
            }
        }

        // ── Location ───────────────────────────────────────────────────────
        if ($request->filled('country')) {
            $isCountry = Country::where('code', $request->country)->first();
            if ($isCountry) {
                $user->country = $request->country;
            }
        }

        if ($request->filled('state') && $request->filled('country')) {
            $isState = States::where('country_code', $request->country)
                             ->where('name', $request->state)
                             ->first();
            if ($isState) {
                $user->state = $request->state;
            }
        }

        // ── Scalar fields ──────────────────────────────────────────────────
        if ($request->filled('name'))            $user->name            = $request->name;
        if ($request->filled('bio'))             $user->bio             = $request->bio;
        if ($request->filled('city'))            $user->city            = $request->city;
        if ($request->filled('zip_code'))        $user->zip_code        = $request->zip_code;
        if ($request->filled('phone_no'))        $user->phone_no        = $request->phone_no;
        if ($request->filled('address'))         $user->address         = $request->address;
        if ($request->filled('education'))       $user->education       = $request->education;
        if ($request->filled('experience'))      $user->experience      = $request->experience;
        if ($request->filled('skills'))          $user->skills          = $request->skills;
        if ($request->filled('expected_salary')) $user->expected_salary = $request->expected_salary;

        $user->save();

        // Re-fetch through UserResource so avatar URL is fully resolved
        $fresh = User::with(['socials', 'Role'])->find($user->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile updated successfully.',
            'data'    => (new UserResource($fresh))->resolve(),
        ]);
    }

    public function show()
    {
        $id   = auth()->id();
        $user = User::with(['socials', 'Role'])->where('id', $id)->first();
        return new UserResource($user);
    }

    public function updateSmartCv(Request $request)
    {
        $request->validate(['smartcv' => 'required|string']);
        $user = auth()->user();
        $user->smartcv = $request->smartcv;
        $user->save();
        return response()->json([
            'status'  => 'success',
            'message' => 'SmartCV updated',
            'smartcv' => $user->smartcv,
        ]);
    }

    public function deleteAvatar()
    {
        $id   = auth()->id();
        $user = auth()->user();

        if ($user->avatar && strpos($user->avatar, '/uploads/avatars/') === 0) {
            $filePath = public_path($user->avatar);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $user->avatar = null;
        $user->save();

        $fresh = User::with(['socials', 'Role'])->where('id', $id)->first();
        return new UserResource($fresh);
    }

    public function updateSocial(Request $request, $id)
    {
        $soc = SocialMedia::where('user_id', auth()->id())->where('id', $id)->first();
        $soc->value = $request->value;
        $soc->save();
        return okResponse('success');
    }

    public function deleteSocial(Request $request, $id)
    {
        SocialMedia::where('user_id', auth()->id())->where('id', $id)->delete();
        return okResponse('success');
    }

    public function addSocial(Request $request)
    {
        SocialMedia::create([
            'label'   => $request->label,
            'value'   => $request->value,
            'user_id' => auth()->id(),
        ]);
        return okResponse('added');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|confirmed|min:8',
        ]);
        try {
            $user = User::where('email', auth()->user()->email)->first();
            if (!$user) return errorResponse('User not found');
            if (!Hash::check($validated['current_password'], $user->password)) {
                return errorResponse('Current password is invalid');
            }
            User::where('email', auth()->user()->email)
                ->update(['password' => bcrypt($validated['password'])]);
            return okResponse('Password updated');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Password update failed']);
        }
    }

    public function deleteAccount(Request $request)
    {
        $user = User::where('email', auth()->user()->email)->first();
        $user->status = 'deleted';
        $user->save();
        return okResponse('Account deleted');
    }
}
