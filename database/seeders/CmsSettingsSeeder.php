<?php

namespace Database\Seeders;

use App\Models\CmsSetting;
use Illuminate\Database\Seeder;

class CmsSettingsSeeder extends Seeder
{
    /**
     * Seed default CMS content for all platform modules.
     *
     * Merge strategy (safe to re-run without wiping admin edits):
     *   - New keys from $defaults get their default values.
     *   - Keys already edited by an admin keep their values.
     *   - Deleted default keys are preserved (harmless; frontend ignores them).
     */
    public function run(): void
    {
        $modules = $this->getDefaultModules();

        foreach ($modules as $module => $defaults) {
            $existing = CmsSetting::where('module', $module)->first();
            $existingContent = $existing ? ($existing->content ?? []) : [];

            // existing admin edits win over defaults field-by-field.
            $merged = array_merge($defaults, $existingContent);

            CmsSetting::updateOrCreate(
                ['module' => $module],
                ['content' => $merged, 'version' => '1.1.0']
            );
        }

        $this->command->info('CMS settings seeded (' . count($modules) . ' modules, merge strategy).');
    }

    /**
     * Default content for every CMS module.
     *
     * Naming conventions:
     *   _json  suffix → JSON-encoded string (array of items for repeating sections)
     *   _html  suffix → rich HTML string (edited via CKEditor in Admin)
     *   plain key     → single-line text / number / boolean
     *
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultModules(): array
    {
        return [

            // ── Homepage ──────────────────────────────────────────────────────────
            'homepage' => array_merge(
                $this->homepageLegacyFields(),
                $this->homepageHeroFields(),
                $this->homepageFeaturesFields(),
                $this->homepageProductivityFields(),
                $this->homepageCareerServicesFields(),
                $this->homepageProcessFields(),
                $this->homepageLandingFields(),
                $this->homepageTipsFields(),
                $this->homepageHiringFields(),
                $this->homepageReviewsFields()
            ),

            // ── About Us ──────────────────────────────────────────────────────────────
            'about' => array_merge(
                $this->aboutHeroFields(),
                $this->aboutProblemsFields(),
                $this->aboutSolutionsFields(),
                $this->aboutAiFields(),
                $this->aboutCultureFields(),
                $this->aboutUsersFields(),
                $this->aboutVisionFields()
            ),

            // ── Employers (SkillStamp™ landing) ──────────────────────────────────
            'employers' => array_merge(
                $this->employersHeroFields(),
                $this->employersWhyFields(),
                $this->employersBadgesFields(),
                $this->employersHowFields(),
                $this->employersTestimonialsFields(),
                $this->employersJoinFields()
            ),

            // ── Employers → Ordinary ──────────────────────────────────────────────
            'employers_ordinary' => array_merge(
                $this->employersOrdinaryHeroFields(),
                $this->employersOrdinaryFeaturesFields()
            ),

            // ── Employers → SmartStart ────────────────────────────────────────────
            'employers_smartstart' => array_merge(
                $this->employersSmartStartHeroFields(),
                $this->employersSmartStartFeaturesFields()
            ),

            // ── Freelancers → Ordinary ────────────────────────────────────────────
            'freelancers_ordinary' => $this->freelancersOrdinaryFields(),

            // ── Freelancers → SmartStart ──────────────────────────────────────────
            'freelancers_smartstart' => $this->freelancersSmartStartFields(),

            // ── Freelancers → Talent Vault ────────────────────────────────────────
            'freelancers_talentvault' => $this->freelancersTalentVaultFields(),

            // ── Freelancers → In House ────────────────────────────────────────────
            'freelancers_inhouse' => $this->freelancersInHouseFields(),

            // ── Employer Dashboard ────────────────────────────────────────────────
            'employer_dashboard' => [
                'welcome_text'               => 'Welcome back',
                'page_heading'               => 'Employer Dashboard',
                'active_jobs_label'          => 'Active Jobs',
                'new_applicants_label'       => 'New Applicants',
                'profile_views_label'        => 'Profile Views',
                'engagement_label'           => 'Engagement Rate',
                'empty_jobs_message'         => 'You have no active jobs. Post a job to get started.',
                'empty_applicants_message'   => 'No new applicants yet. Check back soon.',
                'applicant_trends_heading'   => 'Applicant Trends',
                'applicant_sources_heading'  => 'Applicant Sources',
            ],

            // ── Employer Dashboard → Post Job ─────────────────────────────────────
            'employer_dashboard_post_job' => [
                'page_heading'         => 'Post a New Job',
                'job_details_section'  => 'Job Details',
                'skills_section'       => 'Skills & Experience',
                'location_section'     => 'Location',
                'job_title_label'      => 'Job Title*',
                'job_title_placeholder'=> 'Ex: Product Designer',
                'job_desc_label'       => 'Job Description*',
                'job_desc_placeholder' => 'Write about the job in details...',
                'job_req_label'        => 'Job Requirement*',
                'job_req_placeholder'  => 'Write about the job requirements...',
                'job_category_label'   => 'Job Category*',
                'job_type_label'       => 'Job Type*',
                'work_type_label'      => 'Work Type*',
                'salary_label'         => 'Salary*',
                'budget_label'         => 'Budgeted Amount*',
                'budget_placeholder'   => 'Enter budgeted amount',
                'skills_label'         => 'Skills*',
                'skills_placeholder'   => 'Add skills and press Enter to save',
                'experience_label'     => 'Experience*',
                'experience_placeholder'=> 'e.g. 2 years',
                'cancel_button'        => 'Cancel',
                'post_button'          => 'Post Job',
                'posting_button'       => 'Submitting...',
            ],

            // ── Employer Dashboard → Talent Vault ─────────────────────────────────
            'employer_dashboard_talent_vault' => [
                'page_heading'              => 'Talent Vault',
                'page_badge'                => 'SmartStart™ Exclusive',
                'page_description'          => 'Access verified freelancers hand-picked from our talent pool.',
                'loading_text'              => 'Loading Talent Vault…',
                'smartstart_only_heading'   => 'SmartStart™ Members Only',
                'smartstart_only_description' => 'The Talent Vault is exclusively available to employers who have activated a SmartStart™ plan. Upgrade to unlock access to our verified talent pool.',
                'smartstart_only_button'    => 'Activate SmartStart™',
                'pricing_heading'           => 'Access the Verified Talent Vault',
                'pricing_description'       => 'Choose a plan to unlock the full verified candidate list.',
                'popular_badge'             => 'Most Popular',
                'candidates_empty'          => 'No candidates found.',
                'view_details_button'       => 'View Details',
                'send_message_button'       => 'Message Candidate',
            ],

            // ── Employer Dashboard → SmartStart Form ──────────────────────────────
            'employer_dashboard_smartstart' => [
                'header_badge'          => 'SmartStart™',
                'header_description'    => 'Tell us about your project. We\'ll hand-pick 3–5 verified freelancers from our talent pool — no searching required.',
                'fee_label'             => 'SmartStart service fee',
                'fee_subtitle'          => 'Covers curated matching + guided hiring',
                'fee_amount'            => '£39',
                'fee_naira'             => '≈ ₦78,000',
                'terms_text'            => 'I agree to Workason\'s terms of service and understand the SmartStart fee is non-refundable once freelancer matching begins.',
                'submit_button'         => 'Submit & pay £39 →',
                'payment_loading_text'  => 'Opening payment...',
                'success_heading'       => 'Payment successful!',
                'success_description'   => 'We\'re reviewing your project and will send you a curated Talent Pack of 3–5 verified freelancers within 24–48 hours. Check your email for next steps.',
            ],

            // ── Candidate Dashboard ───────────────────────────────────────────────
            'candidate_dashboard' => [
                'welcome_text'              => 'Welcome back',
                'applied_jobs_label'        => 'Applied Jobs',
                'saved_jobs_label'          => 'Saved Jobs',
                'profile_strength_label'    => 'Profile Strength',
                'job_engagements_label'     => 'Job Engagements',
                'empty_applied_message'     => 'You have not applied to any jobs yet.',
                'empty_saved_message'       => 'You have no saved jobs.',
                'profile_incomplete_banner' => 'Complete your profile to improve your visibility to employers.',
                'app_activity_heading'      => 'Application Activity',
                'app_status_heading'        => 'Application Status',
                'profile_modal_heading'     => 'Complete Your Profile',
                'profile_modal_description' => 'Welcome! To get the best experience and start getting hired, please set up your profile first. It only takes a few minutes. Without this your profile won\'t be recognised',
                'profile_modal_cta'         => 'Set Up Profile',
                'profile_modal_skip'        => 'Do It Later',
                'saved_jobs_heading'        => 'Saved Jobs',
                'saved_jobs_empty'          => 'No saved jobs found!',
            ],

            // ── Candidate Dashboard → SmartStart Application ──────────────────────
            'candidate_dashboard_smartstart' => [
                'page_heading'              => 'SmartStart™ Application',
                'page_description'          => 'Transform your freelance career with our comprehensive program',
                'step_personal_title'       => 'Personal Details',
                'step_role_title'           => 'Role & Skills',
                'step_portfolio_title'      => 'Portfolio',
                'step_package_title'        => 'Package & Payment',
                'step_availability_title'   => 'Availability & Preferences',
                'step_consent_title'        => 'Consent & Agreement',
                'personal_details_subtitle' => 'Tell us about yourself to get started',
                'role_skills_subtitle'      => 'Define your expertise and experience level',
                'portfolio_subtitle'        => 'Showcase your work and experience',
                'package_subtitle'          => 'Choose your SmartStart™ package',
                'availability_subtitle'     => 'Set your schedule and client preferences',
                'consent_subtitle'          => 'Final agreements to complete your application',
                'terms_checkbox_text'       => 'I agree to the SmartStart™ Terms & Conditions',
                'consent_checkbox_text'     => 'I consent to my profile being listed for job matching',
                'next_button'               => 'Next Step',
                'prev_button'               => 'Previous',
                'submit_button'             => 'Submit Application',
            ],

            // ── Candidate Dashboard → SmartGuide ─────────────────────────────────
            'candidate_dashboard_smartguide' => [
                'modules_heading'       => 'Learning Modules',
                'quickwins_heading'     => 'Quick Wins',
                'ai_tips_heading'       => 'AI Automation Tips',
                'upgrade_button'        => 'Upgrade Role',
                'not_found_message'     => 'Guide not found. Please complete the assessment.',
                'progress_label'        => 'Progress',
                'of_text'               => 'of',
                'modules_completed_text'=> 'modules completed',
            ],

            // ── Footer ────────────────────────────────────────────────────────────
            'footer' => [
                'company_tagline'    => 'Connecting talent with opportunity across Africa.',
                'copyright_text'     => '© 2026 Workason. All rights reserved.',
                'address'            => 'Lagos, Nigeria',
                'support_email'      => 'contact@workason.com',
                'newsletter_title'   => 'Come join us and don\'t miss our latest job vacancies',
                'newsletter_subtitle' => 'By subscribing to our newsletter, you\'re taking a smart step toward transforming your job search.',
            ],

            // ── Global Settings ───────────────────────────────────────────────────
            'global' => [
                'platform_name'       => 'Workason',
                'maintenance_mode'    => false,
                'maintenance_message' => 'We are performing scheduled maintenance. We will be back shortly.',
                'announcement_banner' => '',
                'announcement_active' => false,
                'support_phone'       => '',
                'social_twitter'      => '',
                'social_linkedin'     => '',
                'social_instagram'    => '',
            ],
        ];
    }

    // ── Homepage field groups ─────────────────────────────────────────────────────

    private function homepageLegacyFields(): array
    {
        return [
            'hero_title'               => 'Find Top Talent. Build Great Teams.',
            'hero_subtitle'            => 'Connect with verified freelancers across Africa and beyond.',
            'hero_cta_employer'        => 'Post a Job',
            'hero_cta_candidate'       => 'Find Work',
            'stats_jobs'               => '10,000+ Jobs',
            'stats_employers'          => '5,000+ Employers',
            'stats_candidates'         => '50,000+ Candidates',
            'section_features_title'   => 'Why Workason?',
            'section_features_subtitle' => 'Everything you need to hire or get hired on one platform.',
        ];
    }

    private function homepageHeroFields(): array
    {
        $slides = [
            [
                'title'      => 'Unlock Opportunities in Freelance Work',
                'content'    => 'Hire Verified Virtual Assistants and Editors by the Hour',
                'buttonText' => 'Get Started',
            ],
            [
                'title'      => 'Explore Exciting Opportunities!',
                'content'    => 'Welcome to our job employment platform! Discover a world of career possibilities tailored to your skills and ambitions.',
                'buttonText' => 'Get Started',
            ],
            [
                'title'      => 'Discover Your Ideal Career Match',
                'content'    => 'We believe that every individual has a unique set of talents and aspirations. Our platform matches you with the perfect job opportunity.',
                'buttonText' => 'Search Jobs Now',
            ],
            [
                'title'      => 'Join Our Thriving Community',
                'content'    => 'Join a vibrant community of job seekers, industry professionals, and career experts. Share insights, network with peers.',
                'buttonText' => 'Join Now',
            ],
            [
                'title'      => 'Your Journey Begins Here',
                'content'    => 'Take the first step towards a rewarding career. Whether you\'re a recent graduate or a seasoned professional, we\'ve got you.',
                'buttonText' => 'Get Started Now',
            ],
            [
                'title'      => 'Stay Ahead in Your Career Journey',
                'content'    => 'Access a wealth of career resources, including resume tips, interview guidance, and professional development advice.',
                'buttonText' => 'Explore Resources',
            ],
        ];

        return [
            'hero_slides_json' => json_encode($slides),
        ];
    }

    private function homepageFeaturesFields(): array
    {
        $cards = [
            ['title' => 'SkillStamp™',          'desc' => 'Get premium-skills certification bonus for in-house team',      'iconName' => 'Star',         'iconColor' => '#1E2A3B'],
            ['title' => 'SmartStart',            'desc' => 'Program guiding hands-on progress for early freelancers',       'iconName' => 'Rocket',       'iconColor' => '#4ADE80'],
            ['title' => 'SmartGuide',            'desc' => 'Graduate guide to support upward career movement',              'iconName' => 'GraduationCap','iconColor' => '#F472B6'],
            ['title' => 'AI Matching',           'desc' => 'AI-powered project matching and user retention tools',          'iconName' => 'Bot',          'iconColor' => '#60A5FA'],
            ['title' => 'Escrow & Bidding',      'desc' => 'Built-in escrow and competitive bidding for secure deals',      'iconName' => 'ShieldCheck',  'iconColor' => '#FACC15'],
            ['title' => 'Social Impact Angle',   'desc' => 'Inclusive design empowering underrepresented regions',          'iconName' => 'Globe2',       'iconColor' => '#1E2A3B'],
        ];

        return [
            'features_heading'     => 'Why Choose Workason',
            'features_cta_heading' => 'Ready to Get Started?',
            'features_cta_subtitle' => 'Join thousands of professionals who trust Workason for their freelancing needs.',
            'features_cta_button'  => 'Start Your Journey',
            'features_cards_json'  => json_encode($cards),
        ];
    }

    private function homepageProductivityFields(): array
    {
        $stats = [
            ['label' => 'Jobs',        'value' => '20'],
            ['label' => 'Start Ups',   'value' => '10'],
            ['label' => 'Recruitment', 'value' => '50'],
        ];

        return [
            'productivity_heading'    => 'performance',
            'productivity_highlight'  => 'Our productivity',
            'productivity_stats_json' => json_encode($stats),
        ];
    }

    private function homepageCareerServicesFields(): array
    {
        $services = [
            ['iconKey' => 'teacher', 'title' => 'Courses for Sale',         'description' => 'Learn practical skills from top experts online'],
            ['iconKey' => 'pencil',  'title' => 'Custom Editing',           'description' => 'Professional editing services for your in-house needs.'],
            ['iconKey' => 'users',   'title' => 'Hire Freelancers',         'description' => 'Find top-rated editors and creatives worldwide.'],
            ['iconKey' => 'camera',  'title' => 'Custom Virtual Assistant', 'description' => 'Request tailored support from verified virtual assistants for administrative tasks, research, scheduling, email management, and other business needs.'],
        ];

        return [
            'career_badge'         => 'Find Jobs',
            'career_heading'       => 'One easy step to change',
            'career_highlight'     => 'your future.',
            'career_description'   => 'Take the first step towards a rewarding career. Whether you\'re a recent graduate, a seasoned professional, or exploring new opportunities, our platform has everything you need to embark on your next adventure.',
            'career_button_text'   => 'Learn more',
            'career_button_url'    => '/career-tips',
            'services_heading'     => 'Explore Our Services',
            'services_cards_json'  => json_encode($services),
        ];
    }

    private function homepageProcessFields(): array
    {
        $steps = [
            ['id' => 1, 'imageKey' => 'RegisterImage',      'title' => 'Registration',    'desc' => 'Build your reputation by creating a professional resume'],
            ['id' => 2, 'imageKey' => 'ApplyImage',         'title' => 'Apply for Job',   'desc' => 'Find your dream job and send a resume according to your field.'],
            ['id' => 3, 'imageKey' => 'InterviewImage',     'title' => 'Interview',       'desc' => 'Point out your skills and strengths at the interview.'],
            ['id' => 4, 'imageKey' => 'CongratulationImage','title' => 'Congratulations', 'desc' => 'You have completed step by step; it is time for an employment contract.'],
        ];

        return [
            'process_badge'        => 'Started',
            'process_heading'      => 'The fast and',
            'process_highlight'    => 'simple process.',
            'process_description'  => 'Workason carries the theme of technology in helping you find a Job with an easy and fast process.',
            'process_button_text'  => 'Get Started',
            'process_button_url'   => '/register',
            'process_steps_json'   => json_encode($steps),
        ];
    }

    private function homepageLandingFields(): array
    {
        $steps = [
            ['number' => '1', 'title' => 'Tell us what you need',  'description' => 'We understand your goals and workflow.'],
            ['number' => '2', 'title' => 'Get matched instantly',   'description' => 'SmartStart pairs you with a verified pro.'],
            ['number' => '3', 'title' => 'Work with confidence',   'description' => 'ProofToPay protects your payments.'],
        ];

        $benefits = [
            'UK credibility on your CV',
            'Easier to secure freelance projects',
            'Recognition from an international platform',
        ];

        $features = [
            ['iconName' => 'Zap',    'title' => 'SmartStart™ AI', 'desc' => 'Instant matching'],
            ['iconName' => 'Shield', 'title' => 'SkillStamp™',    'desc' => 'Verified expertise'],
            ['iconName' => 'Users',  'title' => 'ProofToPay',     'desc' => 'Payment protection'],
        ];

        return [
            'landing_heading'             => 'The diaspora-first platform for Verified Virtual Assistants & Editors',
            'landing_subtitle'            => 'SmartStart™ AI matching, SkillStamp™ verification, and ProofToPay protection — all in one service.',
            'landing_cta_primary'         => 'Get Started',
            'landing_cta_secondary'       => 'See How It Works',
            'landing_how_heading'         => 'How It Works',
            'landing_steps_json'          => json_encode($steps),
            'landing_referral_heading'    => 'Work Referral',
            'landing_referral_subheading' => 'Workason UK',
            'landing_referral_description' => 'Completing our training means you can add Workason UK on your CV — a UK-based referral partner that boosts your credibility.',
            'landing_referral_button'     => 'Start Training',
            'landing_why_matters'         => 'Your CV won\'t just show skills — it shows endorsement from a UK-based company, increasing trust with employers and clients worldwide.',
            'landing_benefits_json'       => json_encode($benefits),
            'landing_features_json'       => json_encode($features),
        ];
    }

    private function homepageTipsFields(): array
    {
        return [
            'tips_badge'        => 'Career Tips',
            'tips_heading'      => 'One platform for multiple solutions.',
            'tips_highlight'    => 'Your future.',
            'tips_description'  => 'You can find various solutions just by accessing our platform. Because we are committed to maintaining the quality of user service.',
            'tips_button_text'  => 'Learn more',
            'tips_button_url'   => '/career-tips',
        ];
    }

    private function homepageHiringFields(): array
    {
        $options = [
            ['id' => 1, 'title' => 'Work With Workason Directly', 'desc' => 'Dedicated VA or editor assigned to your account',                   'btn' => 'MANAGED SERVICES',  'imageKey' => 'intro',  'route' => '/candidate-inhouse'],
            ['id' => 2, 'title' => 'Access the Talent Vault',     'desc' => 'Workason-managed onboarding and supervision of freelancers.',        'btn' => 'VIEW FREELANCERS',   'imageKey' => 'Second', 'route' => '/candidate-talentvault'],
            ['id' => 3, 'title' => 'Hire on Our Marketplace',     'desc' => 'Communication via Workason-approved systems',                        'btn' => 'FIND FREELANCERS',   'imageKey' => 'third',  'route' => '/ordinary'],
            ['id' => 4, 'title' => 'SkillStamp Certification',    'desc' => 'Freelancers upskill, pass exams, and get verified.',                 'btn' => 'LEARN MORE',         'imageKey' => 'fourth', 'route' => '/candidate-smartstart'],
            ['id' => 5, 'title' => 'SmartStart Matching',         'desc' => 'AI finds the best talent based on your needs.',                      'btn' => 'GET MATCHED',        'imageKey' => 'fifth',  'route' => '/employers-smartstart'],
        ];

        return [
            'hiring_heading'       => 'Hire verified freelancers safely, with or without our help.',
            'hiring_subtitle'      => 'Get the right talent for your business, stress-free.',
            'hiring_cta_primary'   => 'GET STARTED',
            'hiring_bottom_heading' => 'Ready to find the right freelancer?',
            'hiring_bottom_cta'    => 'GET STARTED NOW',
            'hiring_options_json'  => json_encode($options),
        ];
    }

    private function homepageReviewsFields(): array
    {
        $cards = [
            ['id' => 1, 'name' => 'Chioma O.', 'role' => 'Entrepreneur',          'location' => 'Manchester', 'rating' => 5, 'date' => '2d ago',  'avatar' => 'CO', 'review' => 'Hiring a VA through Workason was the best decision. I finally got my admin tasks under control, emails responded to on time, and my calendar properly managed.', 'category' => 'Virtual Assistant', 'verified' => true],
            ['id' => 2, 'name' => 'Kenny L.',  'role' => 'Tech Founder',          'location' => 'UK',         'rating' => 5, 'date' => '1w ago',  'avatar' => 'JL', 'review' => 'I didn\'t just get a VA; I got a partner who anticipates my needs. It feels like I have a full team behind me.',                                                   'category' => 'Virtual Assistant', 'verified' => true],
            ['id' => 3, 'name' => 'Anita B.',  'role' => 'Marketing Consultant',  'location' => 'UK',         'rating' => 5, 'date' => '2w ago',  'avatar' => 'AB', 'review' => 'Instead of wasting weeks searching for freelancers, I got a verified editor within 48 hours through SmartStart. The process was so smooth.',                        'category' => 'SmartStart',        'verified' => true],
            ['id' => 4, 'name' => 'Olu T.',    'role' => 'Diaspora Entrepreneur', 'location' => 'London',     'rating' => 5, 'date' => '3w ago',  'avatar' => 'OT', 'review' => 'The SmartStart pack saved me. I didn\'t have to interview endlessly—Workason sent me pre-vetted talent who matched my exact needs.',                              'category' => 'SmartStart',        'verified' => true],
            ['id' => 5, 'name' => 'Blessing',  'role' => 'Freelance Video Editor','location' => 'Nigeria',    'rating' => 5, 'date' => '1m ago',  'avatar' => 'BL', 'review' => 'Getting SkillStamped on Workason gave me credibility. I landed my first UK client within a week.',                                                               'category' => 'SkillStamp',        'verified' => true],
            ['id' => 6, 'name' => 'Kelechi',   'role' => 'VA',                    'location' => 'Lagos',      'rating' => 5, 'date' => '1m ago',  'avatar' => 'KE', 'review' => 'As a VA, I\'ve joined platforms before, but none felt this personal. Workason not only got me clients, but also trained me with SmartGuide.',                      'category' => 'SmartGuide',        'verified' => true],
        ];

        return [
            'reviews_heading'              => 'What Our Community Says',
            'reviews_subtitle'             => 'Real stories from freelancers and clients who\'ve transformed their careers and businesses with Workason.',
            'reviews_stat_rating'          => '4.9★',
            'reviews_stat_clients'         => '100',
            'reviews_stat_freelancers'     => '150',
            'reviews_bottom_heading'       => 'Join Thousands of Satisfied Users',
            'reviews_bottom_desc'          => 'Our community continues to grow with freelancers and clients who trust Workason for their professional needs.',
            'reviews_stats_avg'            => '4.9/5',
            'reviews_stats_total'          => '2,847',
            'reviews_stats_satisfaction'   => '98%',
            'reviews_stats_active'         => '1,200+',
            'reviews_cards_json'           => json_encode($cards),
        ];
    }

    // ── About Us field groups ─────────────────────────────────────────────────────

    private function aboutHeroFields(): array
    {
        return [
            'about_hero_heading'               => 'We are here to assist',
            'about_hero_highlight'             => 'in recruiting.',
            'about_intro_heading'              => 'Making marketplace hunt easier',
            'about_intro_description_html'     => '<p>Workason is a next-generation freelance marketplace designed for global clients who want trusted, verified talent without the stress of unreliable freelancers or complicated hiring processes.</p><p>We combine AI matching, SkillStamps™ verification, and our TalentVault to help businesses hire virtual assistants, editors, and digital talent with confidence. Whether you\'re in London, Lagos, or anywhere in the world, Workason connects you to skilled freelancers who have been trained, tested, and verified.</p>',
        ];
    }

    private function aboutProblemsFields(): array
    {
        $problems = [
            ['title' => 'Unverified Talent',  'desc' => 'No guarantee of skills or credentials in traditional platforms'],
            ['title' => 'Missed Deadlines',   'desc' => 'Unreliable delivery timelines impact project success'],
            ['title' => 'Trust Issues',       'desc' => 'Poor job quality and lack of transparency'],
        ];

        return [
            'about_built_heading'      => 'Built for the Modern Workforce',
            'about_built_description'  => 'Workason was created to solve three major problems in today\'s freelance industry',
            'about_problems_json'      => json_encode($problems),
        ];
    }

    private function aboutSolutionsFields(): array
    {
        $solutions = [
            ['name' => 'SkillStamps™', 'desc' => 'A role-specific verification badge issued only after passing real assessments. Each SkillStamp represents proven competency and professional standards.'],
            ['name' => 'SmartStart',   'desc' => 'A comprehensive training pathway that equips freelancers with the skills clients actually need. Build expertise that drives real results.'],
            ['name' => 'TalentVault',  'desc' => 'A dedicated space for verified, reliable talent. Access pre-vetted professionals who meet our rigorous quality standards.'],
        ];

        return [
            'about_solutions_heading'     => 'Our Solution',
            'about_solutions_subtext'     => 'Comprehensive tools designed for excellence',
            'about_solutions_json'        => json_encode($solutions),
            'about_platform_description'  => 'Every feature is designed to ensure high-quality work, fair payments, and complete transparency for both freelancers and employers.',
        ];
    }

    private function aboutAiFields(): array
    {
        $features = [
            ['title' => 'Skills Match',              'desc' => 'Precise alignment of technical and soft skills with job requirements'],
            ['title' => 'Verified Credentials',      'desc' => 'Authenticated SkillStamps and professional certifications'],
            ['title' => 'Completion History',        'desc' => 'Proven track record of successfully delivered projects'],
            ['title' => 'Role-Specific Performance', 'desc' => 'Demonstrated success in similar positions and industries'],
        ];

        return [
            'about_ai_heading'      => 'AI Matching That Works for You',
            'about_ai_subtext'      => 'Our AI engine reads job requirements and instantly recommends the most suitable freelancers',
            'about_ai_features_json' => json_encode($features),
        ];
    }

    private function aboutCultureFields(): array
    {
        return [
            'about_culture_label'       => 'Our culture',
            'about_culture_description' => 'We love what we do and collaborate every day, driven to change the world of work',
        ];
    }

    private function aboutUsersFields(): array
    {
        $freelancerSteps = [
            ['stepNumber' => '01', 'title' => 'Start as an Ordinary Freelancer', 'description' => 'Join the platform, create your profile, and begin your freelancing journey with access to job opportunities.'],
            ['stepNumber' => '02', 'title' => 'Upgrade Through SmartStart',      'description' => 'Get verified, access training, and unlock curated opportunities with comprehensive onboarding support.'],
            ['stepNumber' => '03', 'title' => 'Earn SkillStamps™',              'description' => 'Build credibility through verified skills and completed projects that showcase your expertise.'],
            ['stepNumber' => '04', 'title' => 'Join the TalentVault',           'description' => 'Achieve premium visibility and access higher-value clients as part of our elite talent pool.'],
        ];

        $employerBenefits = [
            ['title' => 'Verified Talent',            'description' => 'Access pre-vetted freelancers from the UK diaspora and beyond, all verified through our SmartStart process.'],
            ['title' => 'SmartStart Hiring Support',  'description' => 'Get expert assistance in finding the perfect match with curated shortlists and AI-powered recommendations.'],
            ['title' => 'Faster Job Turnaround',      'description' => 'Connect with ready-to-work talent quickly, reducing time-to-hire and accelerating project delivery.'],
            ['title' => 'ProofToPay Escrow',          'description' => 'Secure, trusted transactions with our escrow system ensuring peace of mind for both parties.'],
        ];

        return [
            'about_users_heading'               => 'Our Users',
            'about_users_freelancer_tab'        => 'For Freelancers',
            'about_users_employer_tab'          => 'For Employers',
            'about_freelancer_subtitle'         => 'Workason gives freelancers a clearer path to success with step-by-step progression.',
            'about_employer_subtitle'           => 'We support businesses of all sizes from UK diaspora clients to global brands.',
            'about_freelancer_path_heading'     => 'Your Path to Success',
            'about_freelancer_path_description' => 'Progress through our structured upgrade system to unlock better opportunities at every level',
            'about_freelancer_steps_json'       => json_encode($freelancerSteps),
            'about_employer_heading'            => 'Why Employers Choose Workason',
            'about_employer_description'        => 'Everything you need to hire top talent quickly, securely, and with confidence',
            'about_employer_benefits_json'      => json_encode($employerBenefits),
        ];
    }

    private function aboutVisionFields(): array
    {
        return [
            'about_vision_label'       => 'Our Vision',
            'about_vision_description' => 'To become the leading platform for trusted global freelance work—powered by verification, structured learning, and smart matching.',
        ];
    }

    // ── Employers (SkillStamp™ landing) field groups ──────────────────────────────

    private function employersHeroFields(): array
    {
        return [
            'emp_hero_badge'         => 'Verified Badge System',
            'emp_hero_heading'       => 'SkillStamp™',
            'emp_hero_description'   => "A verified badge system that gives freelancers credibility and proof of skill on their profile. Think of it as a 'Verified' badge for skills, not just identity.",
            'emp_hero_cta_primary'   => 'Apply for SkillStamp',
            'emp_hero_cta_secondary' => 'Learn More',
        ];
    }

    private function employersWhyFields(): array
    {
        $freelancerBenefits = [
            ['title' => 'Trust & Credibility', 'description' => "Shows you've been vetted by Workason"],
            ['title' => 'Stand Out',            'description' => 'Differentiate from unverified profiles'],
            ['title' => 'Boosted Visibility',   'description' => 'SkillStamp holders get priority in AI Matching'],
            ['title' => 'Premium Access',       'description' => 'Unlock exclusive features and opportunities'],
        ];

        $clientBenefits = [
            ['title' => 'Hiring Confidence', 'description' => "Know you're hiring verified, quality freelancers"],
            ['title' => 'Faster Decisions',  'description' => 'Quickly identify qualified candidates'],
            ['title' => 'Higher Quality',    'description' => 'Access to pre-vetted, skilled professionals'],
        ];

        return [
            'emp_why_heading'                  => 'Why',
            'emp_why_highlight'                => 'SkillStamp™',
            'emp_why_subtitle'                 => 'SkillStamp benefits both freelancers and clients by creating a trusted ecosystem of verified professionals.',
            'emp_why_freelancer_heading'        => 'For Freelancers',
            'emp_why_client_heading'            => 'For Clients',
            'emp_why_freelancer_benefits_json'  => json_encode($freelancerBenefits),
            'emp_why_client_benefits_json'      => json_encode($clientBenefits),
        ];
    }

    private function employersBadgesFields(): array
    {
        $badgeTypes = [
            ['title' => 'Verified', 'description' => 'Awarded after passing test task or real work review. Boosts profile trust.'],
            ['title' => 'Pro',      'description' => 'Given to top-performing freelancers based on reviews, responsiveness, and consistency.'],
            ['title' => 'Agency',   'description' => 'Identifies high-quality teams and businesses with multiple members.'],
        ];

        return [
            'emp_badges_heading'    => 'Types of',
            'emp_badges_highlight'  => 'SkillStamps',
            'emp_badges_subtitle'   => 'Each badge has a unique meaning and helps freelancers stand out based on their status and performance.',
            'emp_badge_types_json'  => json_encode($badgeTypes),
        ];
    }

    private function employersHowFields(): array
    {
        $steps = [
            ['number' => '01', 'title' => 'Apply for SkillStamp',        'description' => 'Complete 1-2 real jobs on the platform OR submit portfolio/test task. Must follow SmartGuide steps.'],
            ['number' => '02', 'title' => 'Admin Review & Verification',  'description' => 'Our team reviews your work quality or test results to ensure you meet our standards.'],
            ['number' => '03', 'title' => 'Badge Displayed Publicly',     'description' => 'Approved badge appears under your name with a green label, boosting visibility and trust.'],
        ];

        return [
            'emp_how_heading'    => 'How It',
            'emp_how_highlight'  => 'Works',
            'emp_how_subtitle'   => 'Getting your SkillStamp™ is a simple 3-step process designed to verify your skills and expertise.',
            'emp_how_steps_json' => json_encode($steps),
        ];
    }

    private function employersTestimonialsFields(): array
    {
        return [
            'emp_testimonials_badge'       => 'Testimonials',
            'emp_testimonials_heading'     => 'What are they',
            'emp_testimonials_highlight'   => 'saying?',
            'emp_testimonials_description' => 'Our customers have testified to the quality of our services and the support system we offer. Clients say we are easy to talk to and very supportive.',
            'emp_testimonials_button'      => 'View All',
            'emp_testimonials_url'         => '/testimonial',
        ];
    }

    private function employersJoinFields(): array
    {
        return [
            'emp_join_heading'   => 'Come join us and enjoy our',
            'emp_join_highlight' => 'interesting features.',
            'emp_join_button'    => 'Get Started',
            'emp_join_url'       => '/register',
        ];
    }

    // ── Employers → Ordinary field groups ────────────────────────────────────────

    private function employersOrdinaryHeroFields(): array
    {
        return [
            'emp_ord_heading'      => 'Hire Freelancers on the Workason Marketplace',
            'emp_ord_description'  => 'Post a job and receive proposals from freelancers across video editing and virtual assistance. Review profiles, chat securely, and hire with confidence using our escrow system.',
            'emp_ord_button'       => 'Start Hiring',
            'emp_ord_button_url'   => '/login',
            'emp_ord_stats_count'  => '100',
            'emp_ord_stats_text'   => 'employers cutting across various industries have trusted us over the past years.',
        ];
    }

    private function employersOrdinaryFeaturesFields(): array
    {
        $features = [
            ['title' => 'Open Marketplace Access',       'description' => 'Browse thousands of verified freelancers across all industries and find the perfect match for your project.'],
            ['title' => 'Freelancers Submit Proposals',  'description' => 'Receive competitive proposals from qualified freelancers. Compare rates, portfolios, and timelines effortlessly.'],
            ['title' => 'Secure Escrow Payments',        'description' => 'Your funds are held securely in escrow until you approve the completed work. Protection guaranteed.'],
            ['title' => 'Pay Only When Completed',       'description' => "Release payment only when you're 100% satisfied with the deliverables. No risk, no hidden fees."],
            ['title' => 'Transparent Ratings & Reviews', 'description' => 'Make informed decisions with comprehensive ratings and genuine reviews from clients.'],
        ];

        return [
            'emp_ord_features_heading'  => 'Key Features',
            'emp_ord_features_subtitle' => 'Everything you need to hire with confidence and get exceptional results',
            'emp_ord_features_json'     => json_encode($features),
            'emp_ord_best_for'          => 'Clients who want flexibility and competitive pricing.',
        ];
    }

    // ── Employers → SmartStart field groups ──────────────────────────────────────

    private function employersSmartStartHeroFields(): array
    {
        return [
            'emp_ss_heading'      => 'SmartStart – Get Matched with Verified Talent',
            'emp_ss_description'  => 'SmartStart uses AI matching to pair you with pre-screened, SkillStamps™ verified talents based on your exact needs, workflow, and budget — without browsing or trial-and-error.',
            'emp_ss_button'       => 'Start Hiring',
            'emp_ss_button_url'   => '/login',
            'emp_ss_stats_count'  => '23,000',
            'emp_ss_stats_text'   => 'employers cutting across various industries have trusted us over the past years.',
        ];
    }

    private function employersSmartStartFeaturesFields(): array
    {
        $features = [
            ['title' => 'AI-powered matching',                  'description' => 'Get Direct Matching to thousands of verified freelancers across all industries and find the perfect match for your project.'],
            ['title' => 'SkillStamps™ verified talents only',   'description' => 'Receive competitive proposals from qualified freelancers. Compare rates, portfolios, and timelines effortlessly.'],
            ['title' => 'Faster hiring, less risk',             'description' => 'Streamlined process to quickly onboard verified talent with reduced hiring risks and hassle.'],
            ['title' => 'Ongoing quality monitoring',           'description' => 'Continuous oversight and quality checks ensure your project stays on track with consistent standards.'],
            ['title' => 'Ideal for long-term or critical roles','description' => 'Perfect for projects requiring sustained commitment or mission-critical deliverables that demand reliability.'],
        ];

        return [
            'emp_ss_features_heading'  => 'Key Features',
            'emp_ss_features_subtitle' => 'Everything you need to hire with confidence and get exceptional results',
            'emp_ss_features_json'     => json_encode($features),
            'emp_ss_best_for'          => 'Busy founders, professionals, and diaspora clients who want speed + certainty',
        ];
    }

    // ── Freelancers → Ordinary field group ───────────────────────────────────────

    private function freelancersOrdinaryFields(): array
    {
        $steps = [
            ['label' => 'Create a profile'],
            ['label' => 'Access open job listings'],
            ['label' => 'Submit proposals'],
            ['label' => 'Get hired by clients'],
            ['label' => 'Complete work'],
            ['label' => 'Get paid securely'],
        ];

        $included = [
            'Access to open job listings on the marketplace',
            'Ability to submit proposals to clients',
            'Smart CV tools to improve profile presentation',
            'Secure escrow payments',
            'Freedom to work with multiple clients',
            'Fully flexible, self-managed work',
        ];

        $notIncluded = [
            'No SmartStart onboarding',
            'No SkillStamps™ badge on smart CV',
            'No access to the Talent Vault',
            'No priority visibility',
            'No AI-assisted suitability or readiness tools',
            'No training or onboarding support',
        ];

        return [
            'fl_ord_badge'                => 'Open Marketplace Path',
            'fl_ord_heading'              => 'Ordinary Freelancers',
            'fl_ord_description'          => 'Join Workason as an Ordinary Freelancer and access open job opportunities on the marketplace. This path provides flexibility and open access to jobs, allowing clients to choose freelancers based on their profiles and proposals without verification or guaranteed outcomes.',
            'fl_ord_how_heading'          => 'How it works',
            'fl_ord_steps_json'           => json_encode($steps),
            'fl_ord_included_heading'     => 'What you get',
            'fl_ord_included_json'        => json_encode($included),
            'fl_ord_not_included_heading' => 'What is NOT included',
            'fl_ord_not_included_json'    => json_encode($notIncluded),
            'fl_ord_best_for'             => 'Freelancers who want open access and flexibility, are comfortable competing for jobs, and prefer to manage their work independently without additional verification or onboarding.',
            'fl_ord_cta_button'           => 'Join as an Ordinary Freelancer',
            'fl_ord_cta_url'              => '/login',
        ];
    }

    // ── Freelancers → SmartStart field group ─────────────────────────────────────

    private function freelancersSmartStartFields(): array
    {
        $steps = [
            ['label' => 'Complete SmartStart onboarding'],
            ['label' => 'Complete preparation tools'],
            ['label' => 'Achieve SmartStart status'],
            ['label' => 'Appear across Workason opportunities'],
            ['label' => 'Work with clients when selected'],
            ['label' => 'Get paid securely'],
        ];

        $included = [
            'Structured SmartStart™ onboarding and readiness process',
            'Access to SmartGuide™ (work standards, expectations, and best practices)',
            'Smart CV tools to improve professional presentation',
            'SkillStamp™ verification (role-based, where applicable)',
            'Optional courses and learning resources',
            'AI-assisted features that support visibility and suitability',
            'Eligibility to be featured within Workason listings, including the Talent Vault',
            'Access to open job listings on the marketplace',
            'Ability to submit proposals to clients',
            'Smart CV tools to improve profile presentation',
            'Secure escrow payments',
            'Freedom to work with multiple clients',
            'Fully flexible, self-managed work',
        ];

        return [
            'fl_ss_badge'            => 'Verified & Prepared Path',
            'fl_ss_heading'          => 'SmartStart Talents',
            'fl_ss_description'      => 'Join Workason as a SmartStart™ Talent and complete a structured onboarding process designed to prepare you for professional work on the platform.',
            'fl_ss_how_heading'      => 'How it works',
            'fl_ss_steps_json'       => json_encode($steps),
            'fl_ss_included_heading' => 'What you get through SmartStart™',
            'fl_ss_included_json'    => json_encode($included),
            'fl_ss_cta_button'       => 'Apply for SmartStart',
            'fl_ss_cta_url'          => '/login',
        ];
    }

    // ── Freelancers → Talent Vault field group ───────────────────────────────────

    private function freelancersTalentVaultFields(): array
    {
        $accessOptions = [
            ['duration' => '3 days'],
            ['duration' => '7 days', 'popular' => true],
            ['duration' => '14 days'],
        ];

        $keyFeatures = [
            ['label' => 'Pre-vetted, verified talents only'],
            ['label' => 'No public competition'],
            ['label' => 'Faster shortlisting'],
            ['label' => 'Ideal for agencies, busy professionals, and HR teams'],
        ];

        $whatYouGet = [
            'Access to curated, verified Workason talents',
            'View smart CVs and detailed profiles',
            'Browse comprehensive portfolios',
            'Check SkillStamps™ scores and verifications',
            'Shortlist candidates directly',
            'Private browsing with no public competition',
            'Fast-track your hiring process',
        ];

        return [
            'fl_tv_badge'                 => 'Exclusive Access',
            'fl_tv_heading'               => 'Access the Talent Vault',
            'fl_tv_description'           => 'Browse a curated vault of verified Workason talents for a limited time. View smart CVs, profiles, portfolios, SkillStamps™ scores, and shortlist candidates directly.',
            'fl_tv_access_label'          => 'Access Options:',
            'fl_tv_access_options_json'   => json_encode($accessOptions),
            'fl_tv_button'                => 'View Talent Vault',
            'fl_tv_button_url'            => '/login',
            'fl_tv_best_for'              => 'Clients who want control + privacy without full outsourcing',
            'fl_tv_features_heading'      => 'Key features',
            'fl_tv_features_json'         => json_encode($keyFeatures),
            'fl_tv_what_you_get_heading'  => 'What you get with Talent Vault access',
            'fl_tv_what_you_get_json'     => json_encode($whatYouGet),
            'fl_tv_talents_heading'       => 'Meet our talents',
            'fl_tv_talents_subtitle'      => 'A glimpse of verified professionals in the vault',
            'fl_tv_cta_heading'           => 'Ready to unlock premium talent?',
            'fl_tv_cta_description'       => 'Get exclusive access to verified professionals. No crowds, no delays—just quality candidates ready to hire.',
            'fl_tv_cta_button'            => 'View Talent Vault',
            'fl_tv_cta_url'               => '/login',
        ];
    }

    // ── Freelancers → In House field group ───────────────────────────────────────

    private function freelancersInHouseFields(): array
    {
        $keyFeatures = [
            ['label' => 'Dedicated in-house VA or editor',            'desc' => 'A single, consistent professional assigned exclusively to your work.'],
            ['label' => 'Workason-managed supervision',               'desc' => 'Our team oversees performance, attendance, and output quality daily.'],
            ['label' => 'Communication via Workason systems only',    'desc' => 'Streamlined, accountable comms — no scattered threads or lost context.'],
            ['label' => 'Quality control & replacements handled',     'desc' => 'Not happy? We replace fast — zero downtime on your end.'],
            ['label' => 'Monthly or project-based plans',             'desc' => 'Choose the engagement model that fits your workflow and budget.'],
        ];

        $steps = [
            ['step' => '01', 'title' => 'Tell us what you need', 'desc' => 'Share your role requirements, preferred skills, and working hours.'],
            ['step' => '02', 'title' => 'We match & assign',     'desc' => 'Workason selects from our vetted pool and assigns your dedicated talent.'],
            ['step' => '03', 'title' => 'We supervise daily',    'desc' => 'Our managers handle check-ins, KPIs, and performance reviews for you.'],
            ['step' => '04', 'title' => 'You focus on growth',   'desc' => 'Receive work outputs and reports — zero people management required.'],
        ];

        $whatYouGet = [
            'Dedicated VA or editor assigned to you',
            'Full supervision by Workason management',
            'All communications through official Workason channels',
            'Weekly performance reports delivered to you',
            'Quality control enforced at every stage',
            'Instant replacements if issues arise',
            'Monthly or project-based billing flexibility',
            'Zero people management required from you',
        ];

        $plans = [
            [
                'id'       => 'va',
                'title'    => 'VA Services',
                'subtitle' => 'Virtual Assistant · Managed',
                'price'    => '249',
                'naira'    => '498,000',
                'tagline'  => 'Delegate with confidence. Your assistant is supervised, accountable, and ready to work.',
                'hours'    => '20–25 hours/month',
                'items'    => ['Email management', 'Calendar scheduling', 'Admin support', 'Customer support', 'Basic research', 'Light social media posting'],
            ],
            [
                'id'       => 'editor',
                'title'    => 'Video Editor',
                'subtitle' => 'Content Editor · Managed',
                'price'    => '279',
                'naira'    => '558,000',
                'tagline'  => 'Consistent, high-quality content without delays or back-and-forth stress.',
                'hours'    => '12–16 short-form videos/month',
                'items'    => ['Cuts, transitions & captions', 'Basic motion graphics', 'Formatting for Reels, TikTok & Shorts', 'Each video up to 60–90 seconds', 'Fully managed by Workason'],
            ],
        ];

        return [
            'fl_ih_badge'                 => 'In-House · Managed by Workason',
            'fl_ih_heading'               => 'Work with Workason',
            'fl_ih_heading_highlight'     => 'Directly',
            'fl_ih_description'           => 'Let Workason handle everything. We assign, manage, and supervise dedicated virtual assistants and editors on your behalf — so you focus on growth, not people management.',
            'fl_ih_plan_label'            => 'Plan Type:',
            'fl_ih_plan_monthly'          => 'Monthly Plan',
            'fl_ih_plan_project'          => 'Project-Based',
            'fl_ih_button'                => 'Get Managed Services',
            'fl_ih_button_url'            => '/contact',
            'fl_ih_best_for'              => 'Businesses that want hands-off execution with accountability.',
            'fl_ih_features_heading'      => 'Key features',
            'fl_ih_features_json'         => json_encode($keyFeatures),
            'fl_ih_how_heading'           => 'How it works',
            'fl_ih_how_subtitle'          => 'From brief to execution in four simple steps — fully handled by Workason.',
            'fl_ih_steps_json'            => json_encode($steps),
            'fl_ih_what_you_get_heading'  => 'What you get with Managed Services',
            'fl_ih_what_you_get_json'     => json_encode($whatYouGet),
            'fl_ih_pricing_badge'         => 'In-House Pricing',
            'fl_ih_pricing_heading'       => 'From £249/month',
            'fl_ih_pricing_description'   => 'One flat monthly fee. No hidden costs. Your dedicated professional, fully supervised by Workason.',
            'fl_ih_plans_json'            => json_encode($plans),
            'fl_ih_cta_badge'             => 'Fully Managed',
            'fl_ih_cta_heading'           => 'Ready for hands-off execution?',
            'fl_ih_cta_description'       => 'Stop managing people. Start scaling your business. Workason handles everything — from assignment to accountability.',
            'fl_ih_cta_button'            => 'Get Managed Services',
            'fl_ih_cta_url'               => '/login',
        ];
    }
}
