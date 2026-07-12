<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Utilities\ProfileUpdateUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileUpdateUtility $profileUtility
    ) {}

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
                Rule::unique('users', 'email')->ignore($user->id),// Ensures the email is unique, ignoring the current user's email. users is the table name, email is the column name, and $user->id is the ID of the currently authenticated user. This prevents validation errors when a user updates their profile without changing their email address.
            ],
        ]);

        $result = $this->profileUtility->updateProfile(
            $user,
            $validated['full_name'],
            $validated['email']
        );

        // Flash message about verification if email changed
        if ($result['email_changed']) {
            session()->flash('warning', 'Please verify your new email address. Check your inbox for a verification link.');
        }

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

       $path = $this->profileUtility->uploadProfilePicture(
            $user,
            $request->file('profile_picture')
        );

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
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('message', 'Account deleted.');
    }
}
