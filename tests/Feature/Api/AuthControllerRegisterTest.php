<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and groups
        Role::create(['role_name' => 'Administrator', 'description' => 'Admin role']);
        Role::create(['role_name' => 'Lecturer', 'description' => 'Lecturer role']);
        Role::create(['role_name' => 'Student', 'description' => 'Student role']);
        Role::create(['role_name' => 'Member', 'description' => 'Member role']);
        
        Group::create(['group_name' => 'Default Group', 'description' => 'Default group']);
    }

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201)
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
                    'email_verified_at',
                    'last_active_at',
                ],
            ])
            ->assertJson([
                'message' => 'Registration successful',
                'user' => [
                    'full_name' => 'John Doe',
                    'email' => 'john@example.com',
                    'role' => 'Student',
                    'group' => 'Default Group',
                ],
            ]);

        // Verify user exists in database
        $this->assertDatabaseHas('users', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Verify token is returned
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_registration_requires_full_name(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_name']);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'not-an-email',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_unique_email(): void
    {
        // Create first user
        User::create([
            'full_name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
        ]);

        // Try to register with same email
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'DifferentPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_strong_password(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_assigns_student_role(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $studentRole = Role::where('role_name', 'Student')->first();

        $this->assertEquals($studentRole->id, $user->role_id);
    }

    public function test_registration_assigns_default_group(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $defaultGroup = Group::where('group_name', 'Default Group')->first();

        $this->assertEquals($defaultGroup->id, $user->group_id);
    }

    public function test_registration_creates_api_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_registration_fails_without_required_role(): void
    {
        // Delete Student role
        Role::where('role_name', 'Student')->delete();

        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Required role or group not found in database. Please contact administrator.',
            ]);
    }

    public function test_registration_fails_without_required_group(): void
    {
        // Delete Default Group
        Group::where('group_name', 'Default Group')->delete();

        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Required role or group not found in database. Please contact administrator.',
            ]);
    }
}
