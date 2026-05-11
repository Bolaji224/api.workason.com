<?php

namespace App\Http\Resources;

use App\Models\SocialMedia;
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
        return [
            "id"            => $this->id,
            "name"          => $this->name ?? "",
            "email"         => $this->email ?? "",
            "status"        => strtolower($this->status ?? ""),
            "role" => $this->Role ? $this->Role->title : "",
            "address"       => $this->address ?? "",
            "phone_no"      => $this->phone_no ?? "",
            "avatar" => $this->avatar ?? "",
            "company"       => $this->company_name ?? "",  // ← was $this->company (wrong column)
            "website"       => $this->website ?? "",
            "founded"       => $this->founded ?? "",
            "company_size"  => $this->company_size ?? "",
            "industry"      => $this->industry ?? "",
            "about_company" => $this->about_company ?? "",
            "country"       => $this->country ?? "",
            "city"          => $this->city ?? "",
            "state"         => $this->state ?? "",
            "zipcode"       => $this->zip_code ?? "",      // ← renamed to match frontend
            "wallet"        => $this->wallet ?? "",
            "social_medias" => $this->socialMedias(),
        ];
    }
}