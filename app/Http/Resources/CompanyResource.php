<?php

namespace App\Http\Resources;

use App\Models\Country;
use App\Models\SocialMedia;
use App\Models\States;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function socialMedias()
    {
        if ($this->socials->count() == 0) {
            foreach (["Facebook", "Instagram", "Twitter", "LinkedIn", "Youtube"] as $s) {
                SocialMedia::create([
                    "user_id" => $this->id,
                    "label"   => $s,
                    "value"   => "",
                ]);
            }
        }
        return SocialMedia::where("user_id", $this->id)
            ->select("id", "label", "value")
            ->get();
    }

    public function toArray($request)
    {
        $countryCode = $this->country ?? "";
        $countryModel = $countryCode ? Country::where("code", $countryCode)->first() : null;
        $states = $countryCode ? States::where("country_code", $countryCode)->select("id", "name")->get() : [];

        return [
            "id"            => $this->id,
            "name"          => $this->name ?? "",
            "email"         => $this->email ?? "",
            "status"        => strtolower($this->status ?? ""),
            "role"          => $this->Role ? $this->Role->title : "",
            "address"       => $this->address ?? "",
            "phone_no"      => $this->phone_no ?? "",
            "avatar"        => $this->avatar ?? "",
            "company"       => $this->company_name ?? "",
            "website"       => $this->website ?? "",
            "founded"       => $this->founded ?? "",
            "company_size"  => $this->company_size ?? "",
            "industry"      => $this->industry ?? "",
            "about_company" => $this->about_company ?? "",
            "country"       => $countryCode,
            "country_name"  => $countryModel ? $countryModel->name : "",
            "city"          => $this->city ?? "",
            "state"         => $this->state ?? "",
            "states"        => $states,
            "zipcode"       => $this->zip_code ?? "",
            "wallet"        => $this->wallet ?? "",
            "social_medias" => $this->socialMedias(),
        ];
    }
}