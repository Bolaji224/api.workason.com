<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
class JobResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->whenLoaded('company'),
            'work_type' => $this->whenLoaded('worktype', function () {
                return [
                    'id' => $this->worktype->id ?? null,
                    'title' => $this->worktype->title ?? null,
                ];
            }),
            'job_type' => $this->whenLoaded('jobtype', function () {
                return [
                    'id' => $this->jobtype->id ?? null,
                    'title' => $this->jobtype->title ?? null,
                ];
            }),
            'job_role' => $this->job_role,
            'salary' => $this->salary,
            'salary_narration' => $this->naration ?? $this->salary_narration,
            'education' => $this->education,
            'location' => $this->location
                ?: trim("{$this->city} {$this->state} {$this->country}") ?: 'N/A',
            'description' => $this->description,
            'requirements' => $this->requirements,
            'experience' => $this->experience,
            'budget' => $this->budget,
            'date_posted' => $this->created_at ? $this->created_at->toDateString() : $this->date_published,

            // ✅ Add these two lines
            'posted'    => $this->created_at ? $this->created_at->diffForHumans() : 'recently',
            'posted_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,

            'slug' => $this->slug,
            'status' => $this->status,
            'benefits' => $this->benefits,
            'skills' => $this->skills
                ? (is_array($this->skills)
                    ? $this->skills
                    : explode(',', $this->skills))
                : [],
            'departments' => $this->whenLoaded('departments', function () {
                return $this->departments->map(function ($dept) {
                    return $dept->department ?? $dept;
                });
            }),
            'applicants' => $this->whenLoaded('applicants', function () {
                return $this->applicants;
            }),
        ];
    }
}