<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores OTP codes for the API (desktop client) password reset flow.
 *
 * The web interface uses Laravel's built-in password_reset_tokens table
 * with URL-based tokens. The API uses a 6-digit OTP the user types directly
 * inside the desktop app, so they never have to leave the application to
 * click a link in a browser.
 *
 * Security properties:
 *  - otp column stores a bcrypt hash — the plaintext code is never persisted.
 *  - expires_at enforces a 10-minute validity window.
 *  - used_at is set immediately on successful use, preventing reuse even
 *    within the validity window (prevents replay attacks).
 *  - Requesting a new OTP deletes all previous unused OTPs for that email,
 *    so only the most-recent code is ever valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('otp');        // bcrypt hash of the 6-digit code
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_password_reset_otps');
    }
};
