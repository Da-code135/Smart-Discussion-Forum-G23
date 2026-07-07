<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class RegistrationOnboardingTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
        Mail::fake();
    }

    // ============================================
    // REGISTRATION FORM
    // ============================================

    public function test_registration_form_is_accessible(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
    }

    public function test_authenticated_user_is_redirected_from_register(): void
    {
        $user = $this->createStudent();
        $response = $this->actingAs($user)->get('/register');
        // Guest middleware redirects authenticated users to dashboard
        $response->assertRedirect(route('dashboard'));
    }

    // ============================================
    // REGISTRATION VALIDATION
    // ============================================

    public function test_registration_requires_full_name(): void
    {
        $response = $this->post('/register', [
            'email' => 'new@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('full_name');
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_requires_unique_email(): void
    {
        $this->createStudent(['email' => 'taken@test.com']);

        $response = $this->post('/register', [
            'full_name' => 'Test User',
            'email' => 'taken@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_requires_strong_password(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'Test User',
            'email' => 'new@test.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'Test User',
            'email' => 'new@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'DifferentPass1',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_valid_registration_stores_session_and_redirects_to_onboarding(): void
    {
        $response = $this->post('/register', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('onboarding'));
        $response->assertSessionHas('registration_data');
    }

    // ============================================
    // ONBOARDING
    // ============================================

    public function test_onboarding_page_shows_rules(): void
    {
        // First register to populate session
        $this->post('/register', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response = $this->get('/onboarding');
        $response->assertStatus(200);
        $response->assertViewIs('auth.onboarding');
    }

    public function test_accepting_onboarding_creates_user_and_logs_in(): void
    {
        // Register first
        $this->post('/register', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        // Accept onboarding with group selection
        $response = $this->post('/onboarding/agree', [
            'group_id' => $this->defaultGroup->id,
        ]);

        // User should be created
        $this->assertDatabaseHas('users', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
        ]);

        // Onboarding agreement should be recorded
        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertDatabaseHas('onboarding_agreements', [
            'user_id' => $user->id,
            'agreed' => true,
        ]);

        // Should redirect to dashboard
        $response->assertRedirect(route('dashboard'));

        // User should be authenticated
        $this->assertAuthenticatedAs($user);
    }

    public function test_accepting_onboarding_assigns_default_role(): void
    {
        $this->post('/register', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $this->post('/onboarding/agree', [
            'group_id' => $this->defaultGroup->id,
        ]);

        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertEquals($this->memberRole->id, $user->role_id);
    }

    public function test_accepting_onboarding_assigns_selected_group(): void
    {
        $this->post('/register', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $this->post('/onboarding/agree', [
            'group_id' => $this->secondGroup->id,
        ]);

        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertEquals($this->secondGroup->id, $user->group_id);
    }

    public function test_declining_onboarding_does_not_create_user(): void
    {
        $this->post('/register', [
            'full_name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response = $this->post('/onboarding/decline');

        // User should NOT be created
        $this->assertDatabaseMissing('users', [
            'email' => 'newuser@test.com',
        ]);

        // Should redirect to register
        $response->assertRedirect(route('register'));

        // Session data should be cleared
        $this->assertNull(session('registration_data'));
    }

    public function test_onboarding_agree_fails_without_session_data(): void
    {
        // No registration session
        $response = $this->post('/onboarding/agree');

        $response->assertRedirect(route('register'));
    }

    // ============================================
    // RATE LIMITING
    // ============================================

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/register', [
                'full_name' => "User $i",
                'email' => "user{$i}@test.com",
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
            ]);
        }

        $response = $this->post('/register', [
            'full_name' => 'User 4',
            'email' => 'user4@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(429);
    }
}
