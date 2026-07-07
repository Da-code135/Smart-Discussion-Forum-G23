<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMailable;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    // ============================================
    // #150: SHOW VERIFY EMAIL FORM
    // ============================================
    public function show()
    {
        // Only show if user is logged in but not verified
        if (Auth::check() && Auth::user()->email_verified_at) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    // ============================================
    // #152: VERIFY EMAIL TOKEN
    // ============================================
    public function verify(Request $request)
    {
        $token = $request->query('token');
        $email = $request->query('email');

        // #152: Validate token against email
        $verification = EmailVerificationToken::where('token', $token)
            ->where('email', $email)
            ->first();

        if (! $verification || ! $verification->isValid()) {
            return redirect()->route('verify-email')
                ->with('error', 'Invalid or expired verification token');
        }

        // #152: Set email_verified_at = now()
        $verification->user->update(['email_verified_at' => now()]);
        $verification->delete();

        return redirect()->route('dashboard')
            ->with('success', 'Email verified successfully!');
    }

    // ============================================
    // #153: RESEND VERIFICATION EMAIL
    // ============================================
    public function resend(Request $request)
    {
        // Require authentication to prevent abuse (sending verification emails to arbitrary addresses)
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Please log in to request a verification email');
        }

        $user = Auth::user();

        if (! $user) {
            return redirect()->back()->with('error', 'User not found');
        }

        // #153: Rate limit to 1 per minute per email
        $key = 'verify-email:'.$user->email;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return redirect()->back()
                ->with('error', "Please wait {$seconds} seconds before requesting another verification email");
        }

        RateLimiter::hit($key, 60); // 1 minute

        // #151: Generate token
        $token = Str::random(64);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'email' => $user->email,
            'expires_at' => now()->addHours(24), // 24 hour expiry
        ]);

        // #153: Send email via queue
        Mail::queue(new VerifyEmailMailable($user, $token));

        return redirect()->back()
            ->with('success', 'Verification email sent. Check your inbox!');
    }
}
