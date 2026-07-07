<?php

namespace Tests\Feature\Web;

use App\Models\BlacklistRecord;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // ============================================
    // LOGIN FORM
    // ============================================

    public function test_login_form_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    // ============================================
    // AUTHENTICATION
    // ============================================

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createStudent();

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_email(): void
    {
        $this->createStudent();

        $response = $this->post('/login', [
            'email' => 'wrong@test.com',
            'password' => 'Password123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createStudent();

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_login_requires_email(): void
    {
        $response = $this->post('/login', [
            'password' => 'Password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'student@test.com',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ============================================
    // BLACKLIST GATE
    // ============================================

    public function test_blacklisted_user_cannot_login(): void
    {
        $user = $this->createStudent(['account_status' => 'blacklisted']);

        BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Test blacklist',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'Password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_blacklisted_user_sees_expiry_date(): void
    {
        $user = $this->createStudent(['account_status' => 'blacklisted']);

        $record = BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Test blacklist',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'Password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ============================================
    // WARNED GATE
    // ============================================

    public function test_warned_user_with_unacknowledged_warning_is_redirected(): void
    {
        $user = $this->createStudent(['account_status' => 'warned']);

        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('warning-acknowledgement'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_warned_user_with_acknowledged_warning_can_login(): void
    {
        $user = $this->createStudent(['account_status' => 'warned']);

        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => true,
            'is_resolved' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    // ============================================
    // LOGOUT
    // ============================================

    public function test_user_can_logout(): void
    {
        $user = $this->createStudent();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ============================================
    // LAST ACTIVE
    // ============================================

    public function test_login_updates_last_active_at(): void
    {
        $user = $this->createStudent(['last_active_at' => null]);

        $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'Password123',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_active_at);
    }

    // ============================================
    // RATE LIMITING
    // ============================================

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'student@test.com',
                'password' => 'WrongPass'.$i,
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'student@test.com',
            'password' => 'WrongPass',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ============================================
    // ROOT REDIRECT
    // ============================================

    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));
    }
}
