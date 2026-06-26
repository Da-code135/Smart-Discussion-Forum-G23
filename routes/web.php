<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\WarningAcknowledgementController;
use Illuminate\Support\Facades\Route;

// ============================================
// GUEST ROUTES (No authentication needed)
// ============================================

Route::get('/', function () {
    return redirect()->route('login');
});

// LOGIN ROUTES
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================
// PROTECTED ROUTES (Authentication required)
// ============================================

Route::middleware('auth')->group(function () {
    // DASHBOARD (#79)
    Route::get('/dashboard', function () {
        return view('auth.dashboard');
    })->name('dashboard');

    // WARNING ACKNOWLEDGEMENT
    Route::get('/warning-acknowledgement', [WarningAcknowledgementController::class, 'show'])
        ->name('warning-acknowledgement');
    Route::post('/warning-acknowledgement', [WarningAcknowledgementController::class, 'acknowledge'])
        ->name('warning-acknowledgement.acknowledge');

    // PROFILE ROUTES (#66-#71)
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/edit', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/picture', [ProfileController::class, 'showPictureUpload'])->name('profile.picture');
    Route::post('/profile/picture', [ProfileController::class, 'uploadPicture'])->name('profile.picture.upload');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::get('/forum', function () {
    return "Forum page (other module)";
})->name('forum.index');

// Group Management
Route::get('/groups', [\App\Http\Controllers\Admin\GroupController::class, 'index'])
    ->name('groups.index');
Route::get('/groups/create', [\App\Http\Controllers\Admin\GroupController::class, 'create'])
    ->name('groups.create');
Route::post('/groups', [\App\Http\Controllers\Admin\GroupController::class, 'store'])
    ->name('groups.store');
Route::get('/groups/{group}/edit', [\App\Http\Controllers\Admin\GroupController::class, 'edit'])
    ->name('groups.edit');
Route::put('/groups/{group}', [\App\Http\Controllers\Admin\GroupController::class, 'update'])
    ->name('groups.update');
Route::delete('/groups/{group}', [\App\Http\Controllers\Admin\GroupController::class, 'destroy'])
    ->name('groups.destroy');
Route::get('/groups/{group}/members', [\App\Http\Controllers\Admin\GroupController::class, 'showMembers'])
    ->name('groups.members');
Route::put('/groups/{group}/members', [\App\Http\Controllers\Admin\GroupController::class, 'updateMembers'])
    ->name('groups.update-members');
Route::post('/groups/bulk-assign', [\App\Http\Controllers\Admin\GroupController::class, 'bulkAssign'])
    ->name('groups.bulk-assign');

// Email Verification Routes
Route::get('/verify-email', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'show'])->name('verify-email');
Route::get('/verify-email/verify', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])->name('verify-email.verify');

Route::middleware('auth')->group(function () {
    Route::post('/verify-email/resend', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'resend'])->name('verify-email.resend');
});

Route::middleware('guest')->group(function () {
    
    // ==================== REGISTRATION & ONBOARDING ROUTES (Task #49) ====================
    
    /**
     * GET /register
     * Task #41: Show registration form
     * Route name: 'register'
     */
    Route::get('/register', [RegisterController::class, 'showRegister'])
        ->name('register');

    /**
     * POST /register
     * Task #43, #44, #45: Store and validate registration data
     * Route name: 'register.store'
     */
    Route::post('/register', [RegisterController::class, 'storeRegister'])
        ->name('register.store');

    /**
     * GET /onboarding
     * Task #46: Show onboarding/platform rules view
     * Route name: 'onboarding'
     */
    Route::get('/onboarding', [RegisterController::class, 'showOnboarding'])
        ->name('onboarding');

    /**
     * POST /onboarding/agree
     * Task #47: Accept terms and create user
     * Route name: 'onboarding.agree'
     */
    Route::post('/onboarding/agree', [RegisterController::class, 'agreeOnboarding'])
        ->name('onboarding.agree');

    /**
     * POST /onboarding/decline
     * Task #48: Decline terms, don't create user
     * Route name: 'onboarding.decline'
     */
    Route::post('/onboarding/decline', [RegisterController::class, 'declineOnboarding'])
        ->name('onboarding.decline');


    // ==================== PASSWORD RESET ROUTES (Task #61) ====================

    /**
     * GET /forgot-password
     * Task #51: Show forgot password form
     * Route name: 'password.request'
     */
    Route::get('/forgot-password', [PasswordController::class, 'showForgotPassword'])
        ->name('password.request');

    /**
     * POST /forgot-password
     * Task #53: Send password reset link
     * Route name: 'password.email'
     */
    Route::post('/forgot-password', [PasswordController::class, 'sendResetLink'])
        ->name('password.email');

    /**
     * GET /reset-password/{token}
     * Task #55: Show password reset form
     * Route name: 'password.reset'
     */
    Route::get('/reset-password/{token}', [PasswordController::class, 'showResetPassword'])
        ->name('password.reset');

    /**
     * POST /reset-password
     * Task #56: Process password reset
     * Route name: 'password.update'
     */
    Route::post('/reset-password', [PasswordController::class, 'resetPassword'])
        ->name('password.update');
});


// Authenticated routes (logged-in users only)
Route::middleware('auth')->group(function () {
    
    // ==================== CHANGE PASSWORD ROUTES (Task #62) ====================

    /**
     * GET /change-password
     * Task #57, #58: Show change password form
     * Route name: 'password.change'
     */
    Route::get('/change-password', [PasswordController::class, 'showChangePassword'])
        ->name('password.change');

    /**
     * POST /change-password
     * Task #59: Process password change
     * Route name: 'password.change.update'
     */
    Route::post('/change-password', [PasswordController::class, 'updatePassword'])
        ->name('password.change.update');
});
