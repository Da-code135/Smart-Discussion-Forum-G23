<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\BlacklistRecord;

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
        $key = 'login-attempts:' . $request->input('email') . '|' . $request->ip();
        $maxAttempts = 5;
        $lockoutSeconds = 30;

  if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
    $seconds = RateLimiter::availableIn($key);

    throw ValidationException::withMessages([
        'email' => "Too many login attempts. Try again in {$seconds} seconds.",
    ]);
}

 RateLimiter::hit($key, $lockoutSeconds);

        // #52: VALIDATE EMAIL & PASSWORD FORMAT
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // #52: LOOK UP USER IN DATABASE
        $user = User::where('email', $request->input('email'))->first();

        // #52: CHECK IF USER EXISTS & PASSWORD MATCHES
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            // Increment failed attempts
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
                $expiryDate = $blacklistRecord->expiry_date->format('M d, Y');
                throw ValidationException::withMessages([
                    'email' => "Your account is blacklisted until {$expiryDate}.",
                ]);
            }
        }

        // #54: WARNED GATE - Check account_status === 'warned'
        if ($user->account_status === 'warned' && !$user->is_acknowledged) {
            // Log them in but redirect to warning acknowledgement first
            Auth::login($user, $request->input('remember'));
            session()->regenerate();
            $user->update(['last_active_at' => now()]);

            // Clear rate limiter on successful login
            RateLimiter::clear($key);

            return redirect()->route('warning-acknowledgement');
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
        $role = $user->role->role_name;
        if ($role === 'Administrator') {
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
