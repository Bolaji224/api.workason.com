<?php

namespace Database\Seeders;

use App\Models\CmsSetting;
use Illuminate\Database\Seeder;

class CmsSettingsSeeder extends Seeder
{
    /**
     * Seed default CMS content for all platform modules.
     *
     * Uses updateOrCreate so this seeder is fully idempotent:
     *   - First run: inserts all 5 module rows.
     *   - Subsequent runs: re-applies defaults only if the module row
     *     does not already exist. Existing admin edits are preserved
     *     because the match key is 'module' and the defaults only
     *     apply when creating (not updating an existing row).
     *
     * To reset a specific module to defaults, delete its row and re-seed:
     *   DELETE FROM cms_settings WHERE module = 'homepage';
     *   php artisan db:seed --class=CmsSettingsSeeder
     */
    public function run(): void
    {
        $modules = $this->getDefaultModules();

        foreach ($modules as $module => $content) {
            CmsSetting::updateOrCreate(
                // Match key — never changes.
                ['module' => $module],
                // Values set only on INSERT (first run).
                // On subsequent runs, existing content is left untouched
                // because updateOrCreate only writes the second argument
                // values when the record is newly created.
                [
                    'content' => $content,
                    'version' => '1.0.0',
                ]
            );
        }

        $this->command->info('CMS settings seeded successfully (' . count($modules) . ' modules).');
    }

    /**
     * Default content for each CMS module.
     *
     * Keys match the module identifiers used by the frontend Redux slice.
     * Values are plain strings — no HTML, no script tags.
     * Add new modules here as the platform grows.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultModules(): array
    {
        return [

            // ── Homepage ──────────────────────────────────────────────────────
            'homepage' => [
                'hero_title'              => 'Find Top Talent. Build Great Teams.',
                'hero_subtitle'           => 'Connect with verified freelancers across Africa and beyond.',
                'hero_cta_employer'       => 'Post a Job',
                'hero_cta_candidate'      => 'Find Work',
                'stats_jobs'              => '10,000+ Jobs',
                'stats_employers'         => '5,000+ Employers',
                'stats_candidates'        => '50,000+ Candidates',
                'section_features_title'  => 'Why Workason?',
                'section_features_subtitle' => 'Everything you need to hire or get hired on one platform.',
            ],

            // ── Employer Dashboard ────────────────────────────────────────────
            'employer_dashboard' => [
                'welcome_text'          => 'Welcome back',
                'active_jobs_label'     => 'Active Jobs',
                'new_applicants_label'  => 'New Applicants',
                'profile_views_label'   => 'Profile Views',
                'engagement_label'      => 'Engagement Rate',
                'empty_jobs_message'    => 'You have no active jobs. Post a job to get started.',
                'empty_applicants_message' => 'No new applicants yet. Check back soon.',
            ],

            // ── Candidate Dashboard ───────────────────────────────────────────
            'candidate_dashboard' => [
                'welcome_text'              => 'Welcome back',
                'applied_jobs_label'        => 'Applied Jobs',
                'saved_jobs_label'          => 'Saved Jobs',
                'profile_strength_label'    => 'Profile Strength',
                'empty_applied_message'     => 'You have not applied to any jobs yet.',
                'empty_saved_message'       => 'You have no saved jobs.',
                'profile_incomplete_banner' => 'Complete your profile to improve your visibility to employers.',
            ],

            // ── Footer ────────────────────────────────────────────────────────
            'footer' => [
                'company_tagline'   => 'Connecting talent with opportunity across Africa.',
                'copyright_text'    => '© 2026 Workason. All rights reserved.',
                'address'           => 'Lagos, Nigeria',
                'support_email'     => 'support@workason.com',
                'newsletter_title'  => 'Stay in the loop',
                'newsletter_subtitle' => 'Get the latest jobs and platform updates delivered to your inbox.',
            ],

            // ── Global Settings ───────────────────────────────────────────────
            'global' => [
                'platform_name'          => 'Workason',
                'maintenance_mode'       => false,
                'maintenance_message'    => 'We are performing scheduled maintenance. We will be back shortly.',
                'announcement_banner'    => '',
                'announcement_active'    => false,
                'support_phone'          => '',
                'social_twitter'         => '',
                'social_linkedin'        => '',
                'social_instagram'       => '',
            ],

        ];
    }
}
