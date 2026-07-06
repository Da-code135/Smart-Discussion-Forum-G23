<?php

namespace Tests\Feature\Api;

use App\Models\BlacklistRecord;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and groups
        Role::create(['role_name' => 'Administrator', 'description' => 'Admin role']);
        Role::create(['role_name' => 'Student', 'description' => 'Student role']);
        Group::create(['group_name' => 'Default Group', 'description' => 'Default group']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'full_name',
                    'email',
                    'account_status',
                    'role',
                    'group',
                ],
            ])
            ->assertJson([
                'message' => 'Login successful',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_login_fails_with_blacklisted_account(): void
    {
        $user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
            'account_status' => 'blacklisted',
        ]);

        BlacklistRecord::create([
            'user_id' => $user->id,
            'reason' => 'Test blacklist',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account is blacklisted until '.$user->blacklistRecords->first()->expires_at->format('M d, Y').'.',
            ]);
    }

    public function test_login_fails_with_unacknowledged_warning(): void
    {
        $user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
            'account_status' => 'warned',
        ]);

        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => false,
            'is_resolved' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account is warned. Please acknowledge the warning before continuing.',
                'requires_warning_acknowledgement' => true,
            ]);
    }

    public function test_login_succeeds_with_acknowledged_warning(): void
    {
        $user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
            'account_status' => 'warned',
        ]);

        Warning::create([
            'user_id' => $user->id,
            'warning_number' => 1,
            'reason' => 'Test warning',
            'response_deadline' => now()->addDays(7),
            'is_acknowledged' => true, // Acknowledged
            'is_resolved' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful',
            ]);
    }

    public function test_login_rate_limiting(): void
    {
        // Make 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'john@example.com',
                'password' => 'WrongPassword'.$i,
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Too many login attempts. Try again in 30 seconds.',
            ]);
    }

    public function test_login_updates_last_active_at(): void
    {
        $user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
            'last_active_at' => null,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'Password123',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_active_at);
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'password' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        // Verify token is deleted
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_logout_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }
}
