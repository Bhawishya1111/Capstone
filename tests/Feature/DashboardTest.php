<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('WAVe-Lite', false);
    }

    public function test_dashboard_includes_progress_script(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('dashboard-', false);
    }

    public function test_dashboard_reports_average_terminal_scan_duration(): void
    {
        $user = User::factory()->create();
        $base = now()->startOfSecond();

        Scan::forceCreate([
            'user_id' => $user->id,
            'url' => 'https://example.com/completed',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
            'created_at' => $base->copy()->subMinutes(10),
            'updated_at' => $base->copy()->subMinutes(8),
            'completed_at' => $base->copy()->subMinutes(8),
        ]);

        Scan::forceCreate([
            'user_id' => $user->id,
            'url' => 'https://example.com/failed',
            'status' => Scan::STATUS_FAILED,
            'progress' => 80,
            'phase' => Scan::PHASE_FAILED,
            'created_at' => $base->copy()->subMinutes(7),
            'updated_at' => $base->copy()->subMinutes(6),
            'completed_at' => $base->copy()->subMinutes(6),
        ]);

        Scan::forceCreate([
            'user_id' => $user->id,
            'url' => 'https://example.com/running',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 50,
            'phase' => Scan::PHASE_ZAP_ACTIVE,
            'created_at' => $base->copy()->subMinutes(30),
            'updated_at' => $base->copy()->subMinutes(29),
            'completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:01:30', false);
    }
}
