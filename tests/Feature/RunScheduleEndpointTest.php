<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunScheduleEndpointTest extends TestCase
{
    public function test_rejects_request_without_secret_header(): void
    {
        config(['services.cron.secret' => 'test-secret']);

        $this->postJson('/api/internal/run-schedule')->assertForbidden();
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        config(['services.cron.secret' => 'test-secret']);

        $this->postJson('/api/internal/run-schedule', [], ['X-Cron-Secret' => 'wrong-secret'])
            ->assertForbidden();
    }

    public function test_rejects_request_when_secret_is_not_configured(): void
    {
        config(['services.cron.secret' => null]);

        $this->postJson('/api/internal/run-schedule', [], ['X-Cron-Secret' => ''])
            ->assertForbidden();
    }

    public function test_runs_schedule_with_valid_secret(): void
    {
        config(['services.cron.secret' => 'test-secret']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('schedule:run')
            ->andReturn(0);

        $this->postJson('/api/internal/run-schedule', [], ['X-Cron-Secret' => 'test-secret'])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'exit_code' => 0])
            ->assertJsonStructure(['status', 'exit_code', 'ran_at']);
    }
}
