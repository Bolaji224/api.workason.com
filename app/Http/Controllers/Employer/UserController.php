<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Country;
use App\Models\SocialMedia;
use App\Models\States;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UserController extends Controller
{
    public function show()
    {
        $id = auth()->id();
        $user = User::with(["socials", "Role"])->where("id", $id)->first();
        return new CompanyResource($user);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        // Handle country
        if ($request->country) {
            $isCountry = Country::where("code", $request->country)->first();
            if ($isCountry) {
                $user->country = $request->country;
            }
        }

        // Handle state
        if ($request->state) {
            $isState = States::where("country_code", $request->country)
                ->where("name", $request->state)
                ->first();
            if ($isState) {
                $user->state = $request->state;
            }
        }

        $user->name          = $request->name          ?? $user->name;
        $user->email         = $request->email         ?? $user->email;
        $user->website       = $request->website       ?? $user->website;
        $user->phone_no      = $request->phone_no      ?? $user->phone_no;
        $user->founded       = $request->founded       ?? $user->founded;
        $user->company_size  = $request->company_size  ?? $user->company_size;
        $user->about_company = $request->about_company ?? $user->about_company;
        $user->company_name  = $request->name          ?? $user->company_name;
        $user->industry      = $request->industry      ?? $user->industry;
        $user->address       = $request->address       ?? $user->address;
        $user->city          = $request->city          ?? $user->city;
        $user->zip_code      = $request->zipcode       ?? $user->zip_code;

        $user->save();

        return okResponse("profile updated");
    }

public function updateAvatar(Request $request)
{
    $user = auth()->user();

    \Log::info('updateAvatar called', [
        'has_file' => $request->hasFile('file'),
        'all_inputs' => $request->all(),
        'files' => $request->allFiles(),
    ]);

    if ($request->file("file")) {
        $originalName = preg_replace('/\s+/', '_', $request->file('file')->getClientOriginalName());
        $fileName = time() . '_' . $originalName;
        $destination = '/home1/owkvvkte/public_html/website_e166ca66/uploads';
        
        \Log::info('Moving file', [
            'fileName' => $fileName,
            'destination' => $destination,
            'dir_exists' => is_dir($destination),
            'dir_writable' => is_writable($destination),
        ]);

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $request->file('file')->move($destination, $fileName);
        $user->avatar = "https://workason.com/uploads/" . $fileName;
        $user->save();

        \Log::info('Avatar saved', ['avatar' => $user->avatar]);

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully.',
            'data' => $user,
        ]);
    }

    \Log::info('No file found in request');

    return response()->json([
        'status' => 'error',
        'message' => 'No file provided.',
    ]);
}

    public function deleteAvatar()
    {
        $user = auth()->user();

        if ($user->avatar && File::exists(public_path($user->avatar))) {
            File::delete(public_path($user->avatar));
        }

        $user->avatar = null;
        $user->save();

        $updated = User::with(["socials", "Role"])->where("id", auth()->id())->first();
        return new CompanyResource($updated);
    }

    public function updateSocial(Request $request, $id)
    {
        $soc = SocialMedia::where("user_id", auth()->id())->where("id", $id)->first();

        if (!$soc) {
            return errorResponse("Social link not found");
        }

        $soc->value = $request->value;
        $soc->save();

        return okResponse("social link updated");
    }

    public function deleteSocial($id)
    {
        $deleted = SocialMedia::where("user_id", auth()->id())->where("id", $id)->delete();

        if (!$deleted) {
            return errorResponse("Social link not found");
        }

        return okResponse("social link deleted");
    }

    public function addSocial(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:100',
            'value' => 'required|string|max:500',
        ]);

        SocialMedia::create([
            "label"   => $request->label,
            "value"   => $request->value,
            "user_id" => auth()->id(),
        ]);

        return okResponse("social link added");
    }
}