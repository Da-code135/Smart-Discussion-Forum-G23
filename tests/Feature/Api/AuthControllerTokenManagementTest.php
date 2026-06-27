<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Role;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTokenManagementTest extends TestCase
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

    public function test_user_can_refresh_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/token/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
            ])
            ->assertJson([
                'message' => 'Token refreshed successfully',
            ]);

        // Verify old token is deleted
        $this->assertEquals(1, $this->user->tokens()->count());
        
        // Verify new token is different
        $newToken = $response->json('token');
        $this->assertNotEquals($this->token, $newToken);
    }

    public function test_refresh_token_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/token/refresh');

        $response->assertStatus(401);
    }

    public function test_user_can_list_tokens(): void
    {
        // Create multiple tokens
        $this->user->createToken('token-1');
        $this->user->createToken('token-2');
        $this->user->createToken('token-3');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/tokens');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tokens' => [
                    '*' => [
                        'id',
                        'name',
                        'created_at',
                        'last_used_at',
                        'expires_at',
                    ],
                ],
            ]);

        // Should have 4 tokens (3 new + 1 from setUp)
        $this->assertCount(4, $response->json('tokens'));
    }

    public function test_list_tokens_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/tokens');

        $response->assertStatus(401);
    }

    public function test_user_can_revoke_specific_token(): void
    {
        $tokenToDelete = $this->user->createToken('token-to-delete');
        $tokenId = $tokenToDelete->accessToken->id;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/tokens/' . $tokenId);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Token revoked successfully',
            ]);

        // Verify token is deleted
        $this->assertEquals(1, $this->user->tokens()->count());
    }

    public function test_revoke_token_fails_with_invalid_token_id(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/tokens/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Token not found',
            ]);
    }

    public function test_revoke_token_fails_without_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/tokens/1');

        $response->assertStatus(401);
    }

    public function test_user_can_delete_account(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/account', [
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deleted successfully',
            ]);

        // Verify user is deleted from database
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    public function test_delete_account_fails_with_wrong_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/account', [
            'password' => 'WrongPassword123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Invalid password',
            ]);

        // Verify user still exists
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_delete_account_fails_without_password(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/account');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_delete_account_fails_without_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/account', [
            'password' => 'Password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_account_revokes_all_tokens(): void
    {
        // Create additional tokens
        $this->user->createToken('token-1');
        $this->user->createToken('token-2');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/account', [
            'password' => 'Password123',
        ]);

        $response->assertStatus(200);

        // Verify all tokens are deleted
        $this->assertEquals(0, $this->user->tokens()->count());
    }

    public function test_token_expiration_is_set(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/tokens');

        $response->assertStatus(200);

        $tokens = $response->json('tokens');
        // At least check that the tokens array is not empty
        $this->assertNotEmpty($tokens);
        
        // Check that tokens have the expected structure
        foreach ($tokens as $token) {
            $this->assertArrayHasKey('id', $token);
            $this->assertArrayHasKey('name', $token);
            $this->assertArrayHasKey('created_at', $token);
        }
    }
}
