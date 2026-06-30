<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and groups
        Role::create(['role_name' => 'Student', 'description' => 'Student role']);
        Group::create(['group_name' => 'Default Group', 'description' => 'Default group']);

        $this->user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andReturn(Password::RESET_LINK_SENT);

        $response = $this->postJson('/api/v1/password/forgot', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset link sent to your email',
            ]);
    }

    public function test_forgot_password_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/password/forgot', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/v1/password/forgot');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/password/forgot', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Password::shouldReceive('reset')
            ->once()
            ->andReturn(Password::PASSWORD_RESET);

        $response = $this->postJson('/api/v1/password/reset', [
            'token' => 'valid-reset-token',
            'email' => $this->user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset successfully',
            ]);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        Password::shouldReceive('reset')
            ->once()
            ->andReturn(Password::INVALID_TOKEN);

        $response = $this->postJson('/api/v1/password/reset', [
            'token' => 'invalid-token',
            'email' => $this->user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Unable to reset password',
            ]);
    }

    public function test_reset_password_requires_token(): void
    {
        $response = $this->postJson('/api/v1/password/reset', [
            'email' => $this->user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_requires_email(): void
    {
        $response = $this->postJson('/api/v1/password/reset', [
            'token' => 'some-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/password/reset', [
            'token' => 'some-token',
            'email' => $this->user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'DifferentPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_strong_password(): void
    {
        $response = $this->postJson('/api/v1/password/reset', [
            'token' => 'some-token',
            'email' => $this->user->email,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'current_password' => 'Password123',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password changed successfully',
            ]);

        // Verify password was actually changed
        $this->user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $this->user->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'current_password' => 'WrongPassword123',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Current password is incorrect',
            ]);

        // Verify password was not changed
        $this->user->refresh();
        $this->assertTrue(Hash::check('Password123', $this->user->password));
    }

    public function test_change_password_requires_current_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_requires_new_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'current_password' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_requires_password_confirmation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'current_password' => 'Password123',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'DifferentPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_requires_strong_new_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'current_password' => 'Password123',
            'new_password' => 'weak',
            'new_password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_requires_different_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/password/change', [
            'current_password' => 'Password123',
            'new_password' => 'Password123', // Same as current
            'new_password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/password/change', [
            'current_password' => 'Password123',
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(401);
    }
}
