<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\EmailVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/
| Protected routes require auth:sanctum middleware
| Rate limited to 60 requests per minute
|
*/

Route::prefix('v1')->group(function () {

    // Apply security headers to all API routes
    Route::middleware('api.security')->group(function () {

        // ============================================
        // PUBLIC ROUTES (No authentication required)
        // ============================================

        /**
         * POST /api/v1/register
         * Register new user and return API token
         */
        Route::post('/register', [AuthController::class, 'register']);

        /**
         * POST /api/v1/login
         * Authenticate user and return API token
         */
        Route::post('/login', [AuthController::class, 'login']);

        /**
         * POST /api/v1/password/forgot
         * Send password reset link to email
         */
        Route::post('/password/forgot', [PasswordController::class, 'forgot']);

        /**
         * POST /api/v1/password/reset
         * Reset password with token
         */
        Route::post('/password/reset', [PasswordController::class, 'reset']);

        // ============================================
        // PROTECTED ROUTES (Authentication required)
        // ============================================

        Route::middleware('auth:sanctum')->group(function () {
            /**
             * POST /api/v1/logout
             * Revoke current API token
             */
            Route::post('/logout', [AuthController::class, 'logout']);

            /**
             * DELETE /api/v1/account
             * Delete user account
             */
            Route::delete('/account', [AuthController::class, 'deleteAccount']);

            /**
             * POST /api/v1/token/refresh
             * Refresh API token
             */
            Route::post('/token/refresh', [AuthController::class, 'refreshToken']);

            /**
             * GET /api/v1/tokens
             * List all active tokens
             */
            Route::get('/tokens', [AuthController::class, 'listTokens']);

            /**
             * DELETE /api/v1/tokens/{tokenId}
             * Revoke specific token
             */
            Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken']);

            /**
             * GET /api/v1/me
             * Get authenticated user data with role and group
             */
            Route::get('/me', [UserController::class, 'me']);

            /**
             * POST /api/v1/profile
             * Update user profile (full_name, email)
             */
            Route::post('/profile', [ProfileController::class, 'update']);

            /**
             * POST /api/v1/password/change
             * Change user password
             */
            Route::post('/password/change', [PasswordController::class, 'change']);

            /**
             * POST /api/v1/email/verify
             * Verify email address
             */
            Route::post('/email/verify', [EmailVerificationController::class, 'verify']);

            /**
             * POST /api/v1/email/resend
             * Resend verification email
             */
            Route::post('/email/resend', [EmailVerificationController::class, 'resend']);
        });
    });
});
