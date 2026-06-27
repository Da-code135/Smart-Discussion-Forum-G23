<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use App\Models\Group;
use App\Models\EmailVerificationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmailVerificationControllerTest extends TestCase
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

        Mail::fake();

        $this->user = User::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123'),
            'role_id' => Role::where('role_name', 'Student')->first()->id,
            'group_id' => Group::where('group_name', 'Default Group')->first()->id,
            'email_verified_at' => null,
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_user_can_verify_email_with_valid_token(): void
    {
        $verificationToken = EmailVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => 'valid-token-123',
            'email' => $this->user->email,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/verify', [
            'token' => 'valid-token-123',
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully',
            ]);

        // Verify token is deleted from database
        $this->assertDatabaseMissing('email_verification_tokens', ['id' => $verificationToken->id]);
    }

    public function test_verify_email_fails_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/verify', [
            'token' => 'invalid-token',
            'email' => $this->user->email,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token',
            ]);

        // Verify email_verified_at is still null
        $this->user->refresh();
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_verify_email_fails_with_expired_token(): void
    {
        EmailVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => 'expired-token',
            'email' => $this->user->email,
            'expires_at' => now()->subHours(24), // Expired
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/verify', [
            'token' => 'expired-token',
            'email' => $this->user->email,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token',
            ]);
    }

    public function test_verify_email_fails_with_wrong_email(): void
    {
        EmailVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => 'valid-token',
            'email' => $this->user->email,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/verify', [
            'token' => 'valid-token',
            'email' => 'wrong@example.com', // Wrong email
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification token',
            ]);
    }

    public function test_verify_email_requires_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/verify', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_verify_email_requires_email(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/verify', [
            'token' => 'some-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verify_email_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/email/verify', [
            'token' => 'some-token',
            'email' => $this->user->email,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_resend_verification_email(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/resend');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification email sent',
            ]);

        // Verify token was created
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
        ]);
    }

    public function test_resend_verification_fails_if_already_verified(): void
    {
        $this->user->update(['email_verified_at' => now()]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/resend');

        // Should return success even if already verified (idempotent)
        $response->assertStatus(200);
    }

    public function test_resend_verification_is_rate_limited(): void
    {
        // Clear any existing rate limits
        RateLimiter::clear('verify-email:' . $this->user->email);

        // First request should succeed
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/resend');

        $response1->assertStatus(200);

        // Second request within 1 minute should be rate limited
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/resend');

        $response2->assertStatus(429)
            ->assertJson([
                'message' => 'Please wait 60 seconds before requesting another verification email',
            ]);
    }

    public function test_resend_verification_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/email/resend');

        $response->assertStatus(401);
    }

    public function test_resend_verification_creates_valid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/email/resend');

        $response->assertStatus(200);

        $token = EmailVerificationToken::where('user_id', $this->user->id)->first();
        
        $this->assertNotNull($token);
        $this->assertEquals($this->user->email, $token->email);
        $this->assertTrue($token->isValid());
    }
}
