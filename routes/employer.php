<?php

use App\Http\Controllers\Employer\JobController;
use App\Http\Controllers\Employer\SavedCandidateController;
use App\Http\Controllers\Employer\UserController;
use App\Http\Controllers\Employer\JobApplicationController;
use App\Http\Controllers\Employer\BrowseCandidatesController;
use App\Http\Controllers\Employer\DashboardController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verified', 'jwt.verify', 'auth:api', 'employer'])->group(function () {
    Route::get('/dashboard-stats', [DashboardController::class, 'stats']);

    Route::controller(UserController::class)->group(function () {
        Route::post('/user/change-password', 'changePassword');
        Route::post('/user/delete', 'deleteAccount');
        Route::get('/profile', 'show');
        Route::post('/profile', 'update');
        Route::get('/profile/delete-avatar', 'deleteAvatar');
        Route::post('/profile/social/{id}', 'updateSocial');
        Route::post('/profile/social-delete/{id}', 'deleteSocial');
        Route::post('/profile/social-add', 'addSocial');
    });

    Route::prefix("jobs")->group(function() {
        Route::get('/', [JobController::class, 'index']);
        Route::post('/', [JobController::class, 'store']);
        Route::post('/share/{id}', [JobController::class, 'shareJob']);
        Route::get('/delete/{id}', [JobController::class, 'deleteJob']);
    });

    Route::prefix('applications')->group(function () {
        Route::get('/', [JobApplicationController::class, 'index']);
        Route::get('/{id}', [JobApplicationController::class, 'show']);
        Route::post('/{id}/update-status', [JobApplicationController::class, 'updateStatus']);
    });

    Route::controller(SavedCandidateController::class)->group(function () {
        Route::get('saved-candidate', 'index');
        Route::post('saved-candidate', 'store');
        Route::get('saved-candidate/delete/{id}', 'destroy');
    });

    Route::controller(BrowseCandidatesController::class)->group(function () {
        Route::get('/browse-candidates', 'index');
        Route::post('/record-payment', 'storePayment');
    });

    // Reviews (employer write)
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/can-review/{freelancerId}', [ReviewController::class, 'canReview']);

});
