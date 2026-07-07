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
            'url' => 'https://example.com/running',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 25,
            'phase' => Scan::PHASE_ZAP_SPIDER,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-poll-url', false);
        $response->assertSee('data-scan-progress-bar', false);
    }

    public function test_dashboard_calculates_average_terminal_scan_duration(): void
    {
        $user = User::factory()->create();
        $startedAt = Carbon::parse('2026-07-07 10:00:00');

        $completedScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/one',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
        ]);
        $completedScan->forceFill([
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
            'completed_at' => $startedAt->copy()->addSeconds(60),
        ])->save();

        $failedScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/two',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
        ]);
        $failedScan->forceFill([
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
            'completed_at' => $startedAt->copy()->addSeconds(180),
        ])->save();

        $runningScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com/running',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 25,
            'phase' => Scan::PHASE_ZAP_SPIDER,
        ]);
        $runningScan->forceFill([
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00', false);
    }
}
