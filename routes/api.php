<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\UploadsController;
use App\Http\Controllers\Candidate\AppliedJobsController;
use App\Http\Controllers\SkillstampController;
use App\Http\Controllers\AdminEmployerController;
use App\Http\Controllers\AdminFreelancerController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmartGuideController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\EmployerPaymentController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\SmartStartController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\CmsSettingsController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
// Handle all OPTIONS preflight requests
Route::options('/{any}', function() {
    return response('', 200);
})->where('any', '.*');

Route::group(['middleware' => 'XssSanitizer'], function () {
    Route::group(['middleware' => 'api', 'prefix' => 'v1'], function ($router) {

    Route::prefix('admin')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    

    Route::middleware(['admin.auth'])->prefix('admin')->group(function () {

            Route::post('/logout', [AdminAuthController::class, 'logout']);
            Route::get('/me', [AdminAuthController::class, 'me']);

            Route::get('/payments', [EmployerPaymentController::class, 'getAllPayments']);
            Route::post('/approve-payment', [EmployerPaymentController::class, 'approvePayment']);
            Route::post('/reject-payment', [EmployerPaymentController::class, 'rejectPayment']);
            Route::get('/dashboard', [AdminDashboardController::class, 'index']); // FIXED: Removed /admin prefix (already in group)
            



            Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
            Route::post('/approve-withdrawal', [AdminWithdrawalController::class, 'approve']);
            Route::post('/reject-withdrawal', [AdminWithdrawalController::class, 'reject']);

            Route::get('/employers', [AdminEmployerController::class, 'index']);
            Route::post('/employers/{id}/suspend', [AdminEmployerController::class, 'suspend']);
            Route::post('/employers/{id}/unsuspend', [AdminEmployerController::class, 'unsuspend']);
            Route::delete('/employers/{id}', [AdminEmployerController::class, 'destroy']);

            Route::get('/freelancers', [AdminFreelancerController::class, 'index']);
            Route::get('/freelancers/{id}', [AdminFreelancerController::class, 'show']);
            Route::post('/freelancers/{id}/approve', [AdminFreelancerController::class, 'approve']);
            Route::post('/freelancers/{id}/revoke', [AdminFreelancerController::class, 'revoke']);
            Route::post('/freelancers/{id}/suspend', [AdminFreelancerController::class, 'suspend']);
            Route::post('/freelancers/{id}/unsuspend', [AdminFreelancerController::class, 'unsuspend']);
            Route::delete('/freelancers/{id}', [AdminFreelancerController::class, 'destroy']);
            Route::get('/jobs', [AdminJobController::class, 'index']);
            Route::post('/jobs/approve', [AdminJobController::class, 'approve']);
            Route::post('/jobs/delete', [AdminJobController::class, 'delete']);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::get('/users/{id}', [AdminUserController::class, 'show']);
            Route::post('/users/{id}/approve', [AdminUserController::class, 'approve']);
            Route::get('/reports', [ReportController::class, 'index']);
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
            Route::post('/reports/{id}/resolve', [ReportController::class, 'resolve']);

            // ── CMS Settings (admin) ──────────────────────────────────────────
            Route::prefix('cms')->group(function () {
                // List all modules with full metadata (id, version, timestamps).
                Route::get('/settings', [CmsSettingsController::class, 'adminIndex']);
                // Create or update a single module's content.
                // XssSanitizer is bypassed here because CKEditor fields contain
                // legitimate HTML. The controller applies its own selective
                // sanitization: dangerous tags are stripped from HTML fields,
                // strip_tags() is applied to all plain-text fields.
                Route::put('/settings/{module}', [CmsSettingsController::class, 'update'])
                    ->withoutMiddleware(\App\Http\Middleware\XssSanitizer::class);
            });

        });

        Route::get('test-auth', function (Request $request) {
            return response()->json([
                'auth_user' => auth()->user() ? auth()->user()->id : null,
                'request_user' => $request->user() ? $request->user()->id : null,
            ]);
        })->middleware(['jwt.verify']);

        // Broadcast routes
        Route::post('broadcasting/auth', function (Request $request) {
            $user = auth()->user();
            $channelName = $request->input('channel_name');
            preg_match('/chat\.(\d+)/', $channelName, $matches);
            $chatId = $matches[1] ?? null;

            if (!$user) {
                return response()->json([
                    'error' => 'No authenticated user',
                    'debug' => [
                        'auth_user' => null,
                        'has_token' => $request->header('Authorization') ? true : false,
                        'channel' => $channelName,
                    ]
                ], 403);
            }

            $chat = \App\Models\Chat::find($chatId);
            if (!$chat) {
                return response()->json([
                    'error' => 'Chat not found',
                    'debug' => ['user_id' => $user->id, 'chat_id' => $chatId]
                ], 403);
            }

            $hasAccess = (int)$user->id === (int)$chat->user1 || (int)$user->id === (int)$chat->user2;

            if (!$hasAccess) {
                return response()->json([
                    'error' => 'User not authorized for this chat',
                    'debug' => [
                        'user_id' => $user->id,
                        'chat_id' => $chatId,
                        'chat_user1' => $chat->user1,
                        'chat_user2' => $chat->user2,
                    ]
                ], 403);
            }

            return Broadcast::auth($request);
        })->middleware(['jwt.verify']);

        Route::controller(AuthController::class)->group(function () {
            Route::post('/register', 'register');
            Route::post('/login', 'login');
            Route::get('/login', function () {
                return errorResponse("Unauthenticated", [], 321);
            })->name("login");

            Route::post('/auth/google', [AuthController::class, 'googleLogin']);

            Route::post('/forgot-password', 'forgotPassword');
            Route::post('/verify-token', 'verifyToken');
            Route::post('reset-password', 'resetPassword');
            Route::post('resend-otp', 'resendOtp');

            Route::middleware(['verified', 'jwt.verify'])->group(function () {
                Route::post('/logout', 'logout');
                Route::post('/change-password', 'changePassword');
            });
        });
        
        

        Route::get('/resources', [ResourceController::class, 'resource']);
        Route::get('/jobs', [JobsController::class, 'index']);
        Route::get('/jobs-alert', [JobsController::class, 'alert']);
        Route::post('/apply-job/{jobId}', [JobsController::class, 'applyJob']);
        Route::get('/jobs/saved', [JobsController::class, 'saved']);
        Route::post('/jobs/saved/{id}', [JobsController::class, 'savedPost']);
        Route::post('/jobs/saved/delete/{id}', [JobsController::class, 'deletesaved']);
        Route::get('/jobs/{slug}', [JobsController::class, 'show']);
        Route::get('/job/share/{id}', [JobsController::class, 'shareJob']);
        Route::get('/jobs/similar/{slug}', [JobsController::class, 'similarJobs']);
        Route::get('/fetch-job-types', [JobsController::class, 'fetchJobTypes']);

        Route::prefix("upload")->group(function () {
            Route::post('/file', [UploadsController::class, 'uploadFile']);
        });

        Route::prefix("countries")->group(function () {
            Route::get('/', [CountryController::class, 'index']);
            Route::get('/{code}', [CountryController::class, 'show']);
        });

        Route::post('/dispute/submit', [DisputeController::class, 'store'])->name('dispute.submit');
   

        Route::middleware(['verified', 'jwt.verify', 'auth:api'])->group(function () {

            // User routes
            Route::controller(UserController::class)->group(function () {
                Route::get('/users', 'index');
                Route::post('/user/change-password', 'changePassword');
                Route::post('/user/delete', 'deleteAccount');
                Route::get('/profile', 'show');
                Route::post('/profile', 'update');
                Route::get('/profile/delete-avatar', 'deleteAvatar');
                Route::post('/profile/social/{id}', 'updateSocial');
                Route::post('/profile/social-delete/{id}', 'deleteSocial');
                Route::post('/profile/social-add', 'addSocial');
                Route::post('/profile/update-smartcv', 'updateSmartCv');
                Route::post('/cv', [CvController::class, 'generate']);
            });
            
            

            // SmartGuide routes
            Route::get('/smartguide', [SmartGuideController::class, 'show']);
            Route::post('/smartguide', [SmartGuideController::class, 'store']);
            Route::get('/smartguide/{guideId}', [SmartGuideController::class, 'showGuideContent']);
            Route::post('/smartguide/{guideId}/progress', [SmartGuideController::class, 'updateProgress']);

            // Skillstamp
            Route::post('/skillstamp/award', [SkillstampController::class, 'award']);

            // Wallet
            Route::get('/candidate/wallet/{userId}', [WalletController::class, 'getWallet']);
            Route::post('/candidate/wallet/generate-token', [WalletController::class, 'generateToken']);
            Route::get('candidate/transactions', [WalletController::class, 'transactionHistory']);

            // Candidate withdrawals routes
            Route::get('/candidate/withdrawal-balance', [WithdrawalController::class, 'getAvailableBalance']);
            Route::get('/candidate/withdrawals', [WithdrawalController::class, 'index']);
            Route::post('/candidate/withdrawals', [WithdrawalController::class, 'store']);
            Route::post('/candidate/withdrawals/{id}/cancel', [WithdrawalController::class, 'cancel']);

            // Employer Payment Routes
            Route::prefix('employer')->group(function () {
                Route::post('/payment-breakdown', [EmployerPaymentController::class, 'getPaymentBreakdown']);
                Route::post('/validate-token', [EmployerPaymentController::class, 'validateToken']);
                Route::post('/fund-wallet', [EmployerPaymentController::class, 'fundWallet']);
                Route::post('/create-milestones', [EmployerPaymentController::class, 'createMilestones']);
                Route::post('/confirm-payment', [EmployerPaymentController::class, 'confirmPayment']);
                Route::get('/payments', [EmployerPaymentController::class, 'getPayments']);
                Route::post('/verify-payment', [EmployerPaymentController::class, 'verifyPayment']);
                Route::post('/send-message', [ChatController::class, 'sendMessage']);
                    Route::post('/smartstart', [SmartStartController::class, 'store'])
    ->withoutMiddleware(\App\Http\Middleware\XssSanitizer::class);
    Route::get('/smartstart/callback', [SmartStartController::class, 'callback'])->name('smartstart.callback');
         Route::get('/smartstart/success', [SmartStartController::class, 'success'])->name('smartstart.success');
    Route::get('/smartstart/failed', [SmartStartController::class, 'failed'])->name('smartstart.failed');

                // Review routes (employer only, auth required)
                Route::post('/reviews', [ReviewController::class, 'store']);
                Route::get('/reviews/can-review/{freelancerId}', [ReviewController::class, 'canReview']);

                Route::post('/approve-work', [EmployerPaymentController::class, 'approveWork']);
                Route::post('/reject-work', [EmployerPaymentController::class, 'rejectWork']);
                Route::post('/approve-milestone', [EmployerPaymentController::class, 'approveMilestone']);
                Route::post('/reject-milestone', [EmployerPaymentController::class, 'rejectMilestone']);
                Route::get('/profile',                     [\App\Http\Controllers\Employer\UserController::class, 'show']);
                Route::post('/profile',                    [\App\Http\Controllers\Employer\UserController::class, 'update']);
                Route::post('/profile/avatar', [\App\Http\Controllers\Employer\UserController::class, 'updateAvatar'])
    ->withoutMiddleware(\App\Http\Middleware\XssSanitizer::class);  // ← before {id}
                Route::get('/profile/delete-avatar',       [\App\Http\Controllers\Employer\UserController::class, 'deleteAvatar']);   // ← before {id}
                Route::post('/profile/social-add',         [\App\Http\Controllers\Employer\UserController::class, 'addSocial']);      // ← before {id}
                Route::post('/profile/social/{id}',        [\App\Http\Controllers\Employer\UserController::class, 'updateSocial']);   // ← {id} routes 
                Route::post('/profile/social-delete/{id}', [\App\Http\Controllers\Employer\UserController::class, 'deleteSocial']);   // ← {id} routes last
            
            });

            Route::get('candidate/applied-jobs', [AppliedJobsController::class, 'index']);

            Route::prefix("/resume")->controller(ResumeController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('update', 'update');
                Route::post('remove-resume', 'removeResume');
                Route::post('update/intro', 'updateIntro');
                Route::get('delete-portolio/{id}', 'deletePortfolio');
            });

            Route::prefix('candidates')->controller(\App\Http\Controllers\Candidate\CandidateController::class)->group(function () {
                Route::get('/{id}', 'show');
                Route::post('/update', 'update');
            });

            Route::prefix("/chat")->controller(ChatController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/{id}', 'show');
                Route::post('send-chat', 'sendMessage');
            });
        });
        

        // ✅ REMOVED DUPLICATE ROUTE (was line 194)

        Route::get('/paystack/public-key', function () {
            return response()->json(['public_key' => config('paystack.public_key')]);
        });
        
        // Reviews (public read, no auth required)
        Route::get('/reviews/freelancer/{id}', [ReviewController::class, 'getFreelancerReviews']);
        Route::get('/reviews/freelancer/{id}/summary', [ReviewController::class, 'getFreelancerSummary']);

        // ── CMS Settings (public — no auth required) ─────────────────────────
        // The kason frontend fetches this once on app startup to hydrate
        // the Redux CMS slice. No sensitive data is exposed here.
        Route::prefix('cms')->group(function () {
            Route::get('/settings',           [CmsSettingsController::class, 'index']);
            Route::get('/settings/{module}',  [CmsSettingsController::class, 'show']);
        });

        Route::post('newsletter', [NewsletterController::class, 'subscribe']);
        Route::post('/contact', [ContactController::class, 'send']);
    });
});
