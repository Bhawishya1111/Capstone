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
        Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 25,
            'phase' => Scan::PHASE_NIKTO,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-poll-url', false);
        $response->assertSee('data-scan-progress-bar', false);
    }

    public function test_dashboard_calculates_average_duration_for_terminal_scans(): void
    {
        $user = User::factory()->create();
        $startedAt = Carbon::parse('2026-07-06 12:00:00');

        $completedScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
            'completed_at' => $startedAt->copy()->addMinute(),
        ]);
        $completedScan->created_at = $startedAt;
        $completedScan->updated_at = $startedAt->copy()->addMinute();
        $completedScan->save();

        $failedScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.org',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
            'completed_at' => $startedAt->copy()->addMinutes(3),
        ]);
        $failedScan->created_at = $startedAt;
        $failedScan->updated_at = $startedAt->copy()->addMinutes(3);
        $failedScan->save();

        $runningScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.net',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 25,
            'phase' => Scan::PHASE_NIKTO,
        ]);
        $runningScan->created_at = $startedAt;
        $runningScan->updated_at = $startedAt;
        $runningScan->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00', false);
    }
}
