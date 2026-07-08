<?php

namespace App\Utilities;

use App\Mail\VerifyEmailMailable;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared utility for profile operations used by both web and API controllers.
 *
 * Consolidates profile update and profile picture upload logic
 * so both interfaces behave identically.
 */
class ProfileUpdateUtility
{
    /**
     * Update the user's profile (full_name and email).
     *
     * If the email changed, a verification token is created and a
     * verification email is queued. The email_verified_at is reset to null.
     *
     * @param  User  $user  The user to update
     * @param  string  $fullName  New full name
     * @param  string  $email  New email address
     * @return array{user: User, email_changed: bool, verification_sent: bool}
     */
    public function updateProfile(User $user, string $fullName, string $email): array
    {
        $emailChanged = $email !== $user->email;
        $verificationSent = false;

        if ($emailChanged) {
            $token = Str::random(64);
            EmailVerificationToken::create([
                'user_id' => $user->id,
                'token' => $token,
                'email' => $email,
                'expires_at' => now()->addHours(24),
            ]);

            Mail::queue(new VerifyEmailMailable($user, $token));
            $verificationSent = true;
        }

        $user->update([
            'full_name' => $fullName,
            'email' => $email,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ]);

        $user->refresh();

        return [
            'user' => $user,
            'email_changed' => $emailChanged,
            'verification_sent' => $verificationSent,
        ];
    }

    /**
     * Upload and set a profile picture for the user.
     *
     * Deletes the old picture if one exists, stores the new file
     * under the `avatars` directory on the `public` disk, and
     * updates the user record.
     *
     * @param  User  $user  The user to update
     * @param  UploadedFile  $file  The uploaded image (jpeg/png, max 2MB)
     * @return string The stored file path
     */
    public function uploadProfilePicture(User $user, UploadedFile $file): string
    {
        // Delete old picture if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Store new picture
        $path = $file->store('avatars', 'public');

        $user->update(['profile_picture' => $path]);

        return $path;
    }
}
