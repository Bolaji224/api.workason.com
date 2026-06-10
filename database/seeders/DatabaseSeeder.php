<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\JobDepartment;
use App\Models\JobType;
use App\Models\Role;
use App\Models\WorkType;
use App\Models\WwphJob;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $departments = [
            "Software Development",
            "Business",
            "Finance & Accounting",
            "IT & Software",
            "Office Productivity",
            "Personal Development",
            "Design",
            "Marketing",
            "Backend Development",
            "Fullstack Development",
            "Frontend Development",
        ];

        foreach ($departments as $department) {
            $isDepartment = Department::where("title", $department)->first();
            if(!$isDepartment) {
                Department::create([
                    "title" => $department
                ]);
            }
        }


        $workTypes = [
            "On-Site", "Hybrid", "Remote"
        ];
        foreach ($workTypes as $worktype) {
            $isWorkType = WorkType::where("title", $worktype)->first();
            if(!$isWorkType) {
                WorkType::create([
                    "title" => $worktype
                ]);
            }
        }

        $workTypes = [
            "Full-time", "Part-time", "Contract"
        ];
        foreach ($workTypes as $worktype) {
            $isWorkType = JobType::where("title", $worktype)->first();
            if(!$isWorkType) {
                JobType::create([
                    "title" => $worktype
                ]);
            }
        }

        $roles = [
            "User", "Company"
        ];
        foreach ($roles as $role) {
            $isRole = Role::where("title", $role)->first();
            if(!$isRole) {
                Role::create([
                    "title" => $role
                ]);
            }
        }


        
        // \App\Models\User::factory(10)->create();
        // \App\Models\WwphJob::factory(100)->create();
        // $jobs = WwphJob::all();
        // foreach ($jobs as $job) {
        //     $jobRand = rand(1, 4);
        //     $jobid = $job->id;
        //     for ($i=1; $i <= $jobRand; $i++) { 
        //         $departmentid = rand(1, 11);
        //         $isExist = JobDepartment::where("wwph_job_id", $jobid)->where("department_id", $departmentid)->first();
        //         if(!$isExist) {
        //             JobDepartment::create([
        //                 "wwph_job_id" => $jobid,
        //                 "department_id" => $departmentid
        //             ]);
        //         }
                
        //     }
        // }
        $jobs = WwphJob::all();
        foreach ($jobs as $job) {
            $job->slug = \Illuminate\Support\Str::slug($job->title);
            $job->date_published = now();
            $job->save();
        }

        // Seed default CMS content for all platform modules.
        // Safe to include here — CmsSettingsSeeder uses updateOrCreate
        // and will not overwrite existing admin edits.
        $this->call(CmsSettingsSeeder::class);
    }
}
