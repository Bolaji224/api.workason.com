<?php

namespace App\Http\Controllers;

use App\Http\Resources\DepartmentJobResource;
use App\Http\Resources\JobResource;
use App\Models\Department;
use App\Models\JobDepartment;
use App\Models\JobType;
use App\Models\SavedJob;
use App\Models\WwphJob;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobsController extends Controller
{
    public function index(Request $request)
    {
        $title    = $request->query("title");
        $location = $request->query("location");
        $jobType  = $request->query("jobType");

        $jobs = WwphJob::with(['company', 'jobtype', 'worktype'])
            ->where("status", "active");

        if ($title != "") {
            $jobs = $jobs->where("title", "LIKE", "%{$title}%");
        }

        // ✅ Search across location, city, state and country
        if ($location != "") {
            $jobs = $jobs->where(function ($q) use ($location) {
                $q->where("location", "LIKE", "%{$location}%")
                  ->orWhere("city",     "LIKE", "%{$location}%")
                  ->orWhere("state",    "LIKE", "%{$location}%")
                  ->orWhere("country",  "LIKE", "%{$location}%");
            });
        }

        if ($jobType != "") {
            $thJobType = JobType::where("title", $jobType)->first();
            if ($thJobType) {
                $jobs = $jobs->where("job_type", $thJobType->id);
            }
        }

        $jobs   = $jobs->get();
        $recent = JobResource::collection($jobs);

        return okResponse("fetched jobs", $recent);
    }

    public function alert(Request $request)
    {
        $title    = $request->query("title");
        $location = $request->query("location");
        $jobType  = $request->query("jobType");

        $appliedJobIds = JobApplication::where("user_id", auth()->user()->id)
            ->pluck("job_id")
            ->toArray();

        \Log::info("Jobs Alert Debug", [
            "user_id"           => auth()->user()->id,
            "applied_ids"       => $appliedJobIds,
            "total_active_jobs" => WwphJob::where("status", "active")->count(),
            "available_jobs"    => WwphJob::where("status", "active")
                                    ->whereNotIn("id", $appliedJobIds)
                                    ->count(),
        ]);

        $jobs = WwphJob::with(['company', 'jobtype', 'worktype', 'departments.department'])
            ->where("status", "active")
            ->whereNotIn("id", $appliedJobIds)
            ->orderBy("id", "DESC");

        if ($title != "") {
            $jobs = $jobs->where("title", "LIKE", "%{$title}%");
        }

        // ✅ Search across location, city, state and country
        if ($location != "") {
            $jobs = $jobs->where(function ($q) use ($location) {
                $q->where("location", "LIKE", "%{$location}%")
                  ->orWhere("city",     "LIKE", "%{$location}%")
                  ->orWhere("state",    "LIKE", "%{$location}%")
                  ->orWhere("country",  "LIKE", "%{$location}%");
            });
        }

        if ($jobType != "") {
            $thJobType = JobType::where("title", $jobType)->first();
            if ($thJobType) {
                $jobs = $jobs->where("job_type", $thJobType->id);
            }
        }

        $jobs   = $jobs->take(10)->get();
        $recent = JobResource::collection($jobs);

        return okResponse("fetched jobs", $recent);
    }

    public function saved()
    {
        $savedJobs = SavedJob::where("user_id", auth()->user()->id)->get();
        $jobs = [];
        foreach ($savedJobs as $savedJob) {
            $job = WwphJob::with(["company", "jobtype", "worktype"])
                ->where("status", "active")
                ->where("id", $savedJob->job_id)
                ->first();
            if ($job) $jobs[] = $job;
        }

        $recent = JobResource::collection($jobs);
        return okResponse("fetched saved jobs", $recent);
    }

    public function savedPost($id)
    {
        if (!SavedJob::where("job_id", $id)->where("user_id", auth()->user()->id)->first()) {
            SavedJob::create([
                "job_id"  => $id,
                "user_id" => auth()->user()->id
            ]);
        }

        return okResponse("Job saved");
    }

    public function deletesaved($id)
    {
        SavedJob::where("job_id", $id)
            ->where("user_id", auth()->user()->id)
            ->delete();

        return okResponse("Job deleted");
    }

    public function applyJob(Request $request, $jobId)
    {
        $request->validate([
            'cv_url'           => 'required|string',
            'experience_years' => 'required|numeric|min:0',
            'reason'           => 'required|string|max:1000',
        ]);

        $user = auth()->user();

        $alreadyApplied = JobApplication::where("job_id", $jobId)
            ->where("user_id", $user->id)
            ->first();

        if ($alreadyApplied) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have already applied for this job.',
            ], 409);
        }

        $application = JobApplication::create([
            'job_id'           => $jobId,
            'user_id'          => $user->id,
            'cv'               => $request->cv_url,
            'experience_years' => $request->experience_years,
            'reason'           => $request->reason,
            'status'           => 'pending',
        ]);

        \Log::info("Job Application Saved", [
            "user_id"        => $user->id,
            "job_id"         => $jobId,
            "application_id" => $application->id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Application submitted successfully.',
            'data'    => $application,
        ]);
    }

    public function fetchJobTypes()
    {
        $jobtypes = JobType::where("status", "active")->get();
        return okResponse("fetched", $jobtypes);
    }
}