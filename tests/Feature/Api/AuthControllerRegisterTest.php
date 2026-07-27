<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected Group $studentGroup;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and groups
        Role::create(['role_name' => 'Administrator', 'description' => 'Admin role']);
        Role::create(['role_name' => 'Lecturer', 'description' => 'Lecturer role']);
        Role::create(['role_name' => 'Student', 'description' => 'Student role']);
        Role::create(['role_name' => 'Member', 'description' => 'Member role']);

        $this->studentGroup = Group::create([
            'group_name' => 'General',
            'description' => 'Default group',
            'group_type' => 'student',
        ]);
    }

    /**
     * Build a valid registration payload, overridable per test.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'group_id' => $this->studentGroup->id,
            'agreed' => true,
        ], $overrides);
    }

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

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
                    'role' => 'Member',
                    'group' => 'General',
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
        $payload = $this->validPayload();
        unset($payload['full_name']);

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_name']);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'email' => 'not-an-email',
        ]));

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
            'group_id' => $this->studentGroup->id,
        ]);

        // Try to register with same email
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'email' => 'existing@example.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'password_confirmation' => 'DifferentPassword123',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_strong_password(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_group_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['group_id']);

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['group_id']);
    }

    public function test_registration_requires_agreement(): void
    {
        $payload = $this->validPayload();
        unset($payload['agreed']);

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['agreed']);
    }

    public function test_registration_rejects_declined_agreement(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'agreed' => false,
        ]));

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You must agree to the platform rules before registering.',
            ]);
    }

    public function test_registration_rejects_non_student_group(): void
    {
        $lecturerGroup = Group::create([
            'group_name' => 'Staff Room',
            'description' => 'Lecturer group',
            'group_type' => 'lecturer',
        ]);

        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'group_id' => $lecturerGroup->id,
        ]));

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Please select a valid student group.',
            ]);
    }

    public function test_registration_assigns_member_role(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $memberRole = Role::where('role_name', 'Member')->first();

        $this->assertEquals($memberRole->id, $user->role_id);
    }

    public function test_registration_assigns_selected_group(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();

        $this->assertEquals($this->studentGroup->id, $user->group_id);
    }

    public function test_registration_records_onboarding_agreement(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();

        $this->assertDatabaseHas('onboarding_agreements', [
            'user_id' => $user->id,
            'agreed' => true,
        ]);
    }

    public function test_registration_creates_api_token(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_registration_fails_without_required_role(): void
    {
        // Delete Member role (default registration role)
        Role::where('role_name', 'Member')->delete();

        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Required role not found in database. Please contact administrator.',
            ]);
    }

    public function test_registration_fails_with_nonexistent_group(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'group_id' => 999999,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['group_id']);
    }
}
