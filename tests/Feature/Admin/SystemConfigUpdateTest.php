<?php

namespace Tests\Feature\Admin;

use App\Models\SystemConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class SystemConfigUpdateTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    /**
     * @return array<string, string> Full valid payload for the web settings form
     */
    private function validPayload(): array
    {
        return [
            'max_login_attempts' => '5',
            'lockout_minutes' => '15',
            'inactivity_warning_days' => '30',
            'warning_response_days' => '7',
            'blacklist_duration_days' => '30',
            'days_before_second_warning' => '14',
            'days_before_blacklist' => '14',
            'classification_review_threshold' => '55',
        ];
    }

    // ============================================
    // SETTINGS PAGE DISPLAYS STORED VALUES
    // ============================================

    public function test_settings_page_displays_values_stored_in_database(): void
    {
        $admin = $this->createSystemAdmin();

        SystemConfig::updateOrCreate(['config_key' => 'max_login_attempts'], ['config_value' => '9']);
        SystemConfig::updateOrCreate(['config_key' => 'classification_review_threshold'], ['config_value' => '65']);

        $response = $this->actingAs($admin)->get(route('admin.system-config.index'));

        $response->assertStatus(200);
        $response->assertSee('value="9"', false);
        $response->assertSee('value="65"', false);
        $response->assertSee('Classification Review Threshold');
    }

    public function test_settings_page_renders_defaults_when_no_config_rows_exist(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->get(route('admin.system-config.index'));

        $response->assertStatus(200);
        $response->assertSee('value="40"', false); // classification_review_threshold default
        $response->assertSee('Quiz Settings');
    }

    // ============================================
    // WEB UPDATE
    // ============================================

    public function test_system_admin_can_update_all_settings_including_classification_threshold(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->put(route('admin.system-config.update'), $this->validPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('system_configs', [
            'config_key' => 'classification_review_threshold',
            'config_value' => '55',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'config_key' => 'max_login_attempts',
            'config_value' => '5',
        ]);
    }

    public function test_unchecked_quiz_late_join_checkbox_persists_zero(): void
    {
        $admin = $this->createSystemAdmin();
        // The migration seeds this key with '0'; flip it on so the test proves it gets turned off
        SystemConfig::updateOrCreate(['config_key' => 'quiz_late_join_allowed'], ['config_value' => '1']);

        // Checkbox omitted from payload simulates an unchecked box
        $this->actingAs($admin)->put(route('admin.system-config.update'), $this->validPayload());

        $this->assertDatabaseHas('system_configs', [
            'config_key' => 'quiz_late_join_allowed',
            'config_value' => '0',
        ]);
    }

    public function test_classification_threshold_above_100_is_rejected(): void
    {
        $admin = $this->createSystemAdmin();

        $payload = array_merge($this->validPayload(), ['classification_review_threshold' => '150']);

        $response = $this->actingAs($admin)->put(route('admin.system-config.update'), $payload);

        $response->assertSessionHasErrors('classification_review_threshold');
        $this->assertDatabaseMissing('system_configs', ['config_key' => 'classification_review_threshold']);
    }

    public function test_group_admin_cannot_update_system_config(): void
    {
        $admin = $this->createGroupAdmin();

        $response = $this->actingAs($admin)->put(route('admin.system-config.update'), $this->validPayload());

        $response->assertStatus(403);
    }

    // ============================================
    // API UPDATE
    // ============================================

    public function test_api_update_accepts_classification_threshold_and_quiz_flag(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/system-config', [
            'classification_review_threshold' => '25',
            'quiz_late_join_allowed' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('system_configs', [
            'config_key' => 'classification_review_threshold',
            'config_value' => '25',
        ]);
        $this->assertDatabaseHas('system_configs', [
            'config_key' => 'quiz_late_join_allowed',
            'config_value' => '1',
        ]);
    }

    public function test_api_update_rejects_invalid_classification_threshold(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/system-config', [
            'classification_review_threshold' => '101',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('classification_review_threshold');
    }
}
