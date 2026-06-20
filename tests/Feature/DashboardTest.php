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
        $scan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/running',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 20,
            'phase' => Scan::PHASE_ZAP_SPIDER,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-scan-row', false);
        $response->assertSee('data-scan-progress-bar', false);
        $response->assertSee(route('scans.progress', $scan), false);
    }

    public function test_dashboard_renders_average_duration_for_terminal_scans(): void
    {
        $user = User::factory()->create();

        $oneMinuteScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/one',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
        ]);
        $oneMinuteScan->forceFill([
            'created_at' => '2026-06-20 12:00:00',
            'completed_at' => '2026-06-20 12:01:00',
        ])->save();

        $threeMinuteScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/two',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
        ]);
        $threeMinuteScan->forceFill([
            'created_at' => '2026-06-20 12:00:00',
            'completed_at' => '2026-06-20 12:03:00',
        ])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00', false);
    }
}
