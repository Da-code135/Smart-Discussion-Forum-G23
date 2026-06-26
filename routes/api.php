<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PasswordController;
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
         * POST /api/v1/login
         * Authenticate user and return API token
         */
        Route::post('/login', [AuthController::class, 'login']);

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
        });
    });
});
