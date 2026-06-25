<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // #66: Show profile editor
    public function edit()
    {
        return view('profile.edit', [
            'user' => Auth::user(),
        ]);
    }

    // #67: Update profile
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        if ($emailChanged) {
    // Generate new verification token
           $token = Str::random(64);
           \App\Models\EmailVerificationToken::create([
              'user_id' => $user->id,
              'token' => $token,
              'email' => $validated['email'],
              'expires_at' => now()->addHours(24),
    ]);

    // Send verification email
         Mail::queue(new \App\Mail\VerifyEmailMailable($user, $token));

    // #156: Flash message about verification
    session()->flash('warning', 'Please verify your new email address. Check your inbox for a verification link.');
}

        $user->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ]);

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully!');
    }

    // #68: Show picture upload
    public function showPictureUpload()
    {
        return view('profile.picture', [
            'user' => Auth::user(),
        ]);
    }

    // #68-#69: Handle picture upload
    public function uploadPicture(Request $request)
    {
        $validated = $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png|max:2048',
        ]);

        $user = Auth::user();

        // Delete old picture if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Store new picture
        $path = $request->file('profile_picture')->store('avatars', 'public');

        $user->update(['profile_picture' => $path]);

        return redirect()->route('profile.picture')->with('success', 'Profile picture updated!');
    }

    // #71: Delete account
    public function destroy(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'password' => 'required|current_password',
        ]);

        Auth::logout();
        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('message', 'Account deleted.');
    }
}
