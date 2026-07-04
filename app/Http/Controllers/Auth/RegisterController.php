<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OnboardingAgreement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Role;
use App\Models\Group;
use App\Mail\WelcomeMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
    /**
     * Show the registration form (Task #42)
     *
     * GET /register
     * Route name: 'register'
     *
     * @return \Illuminate\View\View
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Store registration data in session and validate (Task #43, #44, #45)
     *
     * POST /register
     * Route name: 'register.store'
     *
     * Validation Rules:
     * - full_name: required, string, max 255
     * - email: required, email, unique in users table
     * - password: required, confirmed, min 8, mixed case, at least 1 number
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeRegister(Request $request)
    {
        // Task #43: Validate with email uniqueness
        // Task #44: Password confirmation validation
        // Task #45: Password strength rules
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
            ],
        ], [
            'email.unique' => 'An account with this email already exists.',
            'password.confirmed' => 'Passwords do not match.',
            'password' => 'Password must be at least 8 characters with uppercase, lowercase, and numbers.',
        ]);

        // Store validated data in session (password hashed, never plaintext)
        session(['registration_data' => [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
        ]]);

        // Redirect to onboarding
        return redirect()->route('onboarding');
    }

    /**
     * Show the onboarding (platform rules) view (Task #46)
     *
     * GET /onboarding
     * Route name: 'onboarding'
     *
     * Passes available student groups for the user to select from.
     *
     * @return \Illuminate\View\View
     */
    public function showOnboarding()
    {
        // Get all student-type groups for the dropdown
        $groups = Group::where('group_type', 'student')->get();

        return view('auth.onboarding', compact('groups'));
    }

    /**
     * Accept onboarding agreement and create user account (Task #47)
     *
     * POST /onboarding/agree
     * Route name: 'onboarding.agree'
     *
     * ATOMIC: User creation and agreement are wrapped in a DB transaction.
     * If any step fails (invalid group, validation error, DB exception),
     * the entire transaction rolls back and no user record is created.
     *
     * Creates:
     * 1. User record with role_id = Member, group_id from request
     * 2. OnboardingAgreement record with agreed = true
     * 3. Logs in the user automatically
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function agreeOnboarding(Request $request)
    {
        // Retrieve registration data from session
        $registrationData = session('registration_data');

        // Return error if session data is missing
        if (!$registrationData) {
            return redirect()->route('register')
                ->with('error', 'Registration data expired. Please register again.');
        }

        // Validate that the user selected a valid student group
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        // Look up role by name (dynamic, not hardcoded)
        $role = Role::where('role_name', 'Member')->first();

        if (!$role) {
            return redirect()->route('register')
                ->with('error', 'Required role not found. Please contact administrator.');
        }

        // ATOMIC: Wrap user creation and agreement in a DB transaction
        // If the group is invalid, a DB exception occurs and everything rolls back.
        // No partial user records are ever created.
        $user = DB::transaction(function () use ($registrationData, $role, $validated, $request) {
            // Find the selected group inside the transaction
            $group = Group::findOrFail($validated['group_id']);

            // Create User with the selected group (no fallback)
            $user = User::create([
                'full_name' => $registrationData['full_name'],
                'email' => $registrationData['email'],
                'password' => $registrationData['password_hash'],
                'role_id' => $role->id,
                'group_id' => $group->id,
            ]);

            // Create OnboardingAgreement record
            OnboardingAgreement::create([
                'user_id' => $user->id,
                'agreed' => true,
                'ip_address' => $request->ip(),
                'agreement_version' => config('app.agreement_version', '1.0'),
            ]);

            return $user;
        });

        // Clear session data
        session()->forget('registration_data');

        // Log in the user
        Auth::login($user);

        // Fire registered event (for email verification, etc.)
        event(new Registered($user));

        // Send welcome notification email (SDD 5.1.1)
        Mail::to($user->email)->send(new WelcomeMailable($user));

        // Redirect to dashboard with success message
        return redirect()->route('dashboard')
            ->with('success', 'Welcome to the Smart Discussion Forum! Your account has been created.');
    }

    /**
     * Decline onboarding agreement (Task #48)
     *
     * POST /onboarding/decline
     * Route name: 'onboarding.decline'
     *
     * Creates:
     * 1. OnboardingAgreement record with agreed = false, user_id = null
     * 2. Does NOT create a User record
     * 3. Clears session and redirects to register
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function declineOnboarding(Request $request)
    {
        session()->forget('registration_data');

        return redirect()->route('register')
            ->with('info', 'You have declined the platform rules. You can register again if you change your mind.');
    }
}

