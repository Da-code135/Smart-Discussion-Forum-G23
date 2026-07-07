<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlacklistRecord;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    // ============================================
    // #51: SHOW LOGIN FORM
    // ============================================
    public function showLoginForm()
    {
        return view('auth.login');
    }

    // ============================================
    // #52-#55: HANDLE LOGIN
    // ============================================
    public function authenticate(Request $request)
    {
        // #55: RATE LIMITING CHECK (before anything else)
        // Primary: per-IP + email key
        $key = 'login-attempts:'.$request->input('email').'|'.$request->ip();
        // Secondary: email-only key (prevents IP rotation bypass)
        $emailKey = 'login-attempts-email:'.$request->input('email');
        $maxAttempts = 5;
        $lockoutSeconds = 30;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts) || RateLimiter::tooManyAttempts($emailKey, $maxAttempts)) {
            $seconds = max(RateLimiter::availableIn($key), RateLimiter::availableIn($emailKey));

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // #52: VALIDATE EMAIL & PASSWORD FORMAT
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // #52: LOOK UP USER IN DATABASE
        $user = User::where('email', $request->input('email'))->first();

        // #52: CHECK IF USER EXISTS & PASSWORD MATCHES
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            // Increment failed attempts
            RateLimiter::hit($key, $lockoutSeconds);
            RateLimiter::hit($emailKey, $lockoutSeconds);

            throw ValidationException::withMessages([
                'password' => 'These credentials do not match our records.',
            ]);
        }

        // #53: BLACKLIST GATE - Check account_status === 'blacklisted'
        if ($user->account_status === 'blacklisted') {
            // Find the active blacklist record to show expiry date
            $blacklistRecord = BlacklistRecord::where('user_id', $user->id)
                ->whereNull('lifted_at')
                ->first();

            if ($blacklistRecord) {
                RateLimiter::hit($key, $lockoutSeconds);
                $expiryDate = $blacklistRecord->expires_at->format('M d, Y');
                throw ValidationException::withMessages([
                    'email' => "Your account is blacklisted until {$expiryDate}.",
                ]);
            }
        }

        // #54: WARNED GATE - Check account_status === 'warned'
        if ($user->account_status === 'warned') {
            // Check for unacknowledged warnings
            $warnings = Warning::where('user_id', $user->id)
                ->where('is_acknowledged', false)
                ->first();

            if ($warnings) {
                // Log them in but redirect to warning acknowledgement first
                Auth::login($user, $request->boolean('remember'));
                session()->regenerate();
                $user->update(['last_active_at' => now()]);

                // Clear rate limiter on successful login
                RateLimiter::clear($key);
                RateLimiter::clear($emailKey);

                return redirect()->route('warning-acknowledgement');
            }
        }

        // #52: Auth::login() with remember me
        Auth::login($user, $request->input('remember'));

        // #52: session()->regenerate()
        session()->regenerate();

        // #52: update last_active_at
        $user->update(['last_active_at' => now()]);

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // #52: Redirect by role
        if ($user->isSystemAdmin()) {
            return redirect()->route('admin.dashboard');
        } else {
            return redirect()->route('dashboard');
        }
    }

    // ============================================
    // #56: HANDLE LOGOUT
    // ============================================
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
