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

    public function test_dashboard_calculates_average_duration_for_terminal_scans(): void
    {
        $user = User::factory()->create();
        $startedAt = now()->subMinutes(10);

        Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/complete',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
            'created_at' => $startedAt,
            'updated_at' => $startedAt->copy()->addSeconds(60),
            'completed_at' => $startedAt->copy()->addSeconds(60),
        ]);

        Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/failed',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
            'created_at' => $startedAt,
            'updated_at' => $startedAt->copy()->addSeconds(180),
            'completed_at' => $startedAt->copy()->addSeconds(180),
        ]);

        Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/running',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 20,
            'phase' => Scan::PHASE_ZAP_SPIDER,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
            'completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00');
    }
}
