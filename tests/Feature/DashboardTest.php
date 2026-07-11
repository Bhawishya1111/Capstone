<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $scan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 25,
            'phase' => Scan::PHASE_ZAP_SPIDER,
        ]);
        $scan->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-poll-url', false);
        $response->assertSee('data-scan-progress-bar', false);
    }

    public function test_dashboard_displays_average_terminal_scan_duration(): void
    {
        $user = User::factory()->create();
        $startedAt = Carbon::parse('2026-07-11 12:00:00');

        $completedScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_COMPLETED,
            'completed_at' => $startedAt->copy()->addSeconds(120),
        ]);
        $completedScan->created_at = $startedAt;
        $completedScan->updated_at = $startedAt;
        $completedScan->save();

        $failedScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.org',
            'status' => Scan::STATUS_FAILED,
            'completed_at' => $startedAt->copy()->addSeconds(240),
        ]);
        $failedScan->created_at = $startedAt;
        $failedScan->updated_at = $startedAt;
        $failedScan->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:03:00');
    }
}
