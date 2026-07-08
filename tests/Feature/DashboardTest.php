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
            'phase' => Scan::PHASE_ZAP_SPIDER,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-scan-row', false);
        $response->assertSee('data-scan-progress-bar', false);
        $response->assertSee('data-scan-phase', false);
    }

    public function test_dashboard_renders_terminal_scan_average_duration(): void
    {
        $user = User::factory()->create();

        $completedScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
            'completed_at' => Carbon::parse('2026-07-08 12:02:00'),
        ]);
        $completedScan->forceFill([
            'created_at' => Carbon::parse('2026-07-08 12:00:00'),
            'updated_at' => Carbon::parse('2026-07-08 12:02:00'),
        ])->save();

        $failedScan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.org',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
            'completed_at' => Carbon::parse('2026-07-08 12:14:00'),
        ]);
        $failedScan->forceFill([
            'created_at' => Carbon::parse('2026-07-08 12:10:00'),
            'updated_at' => Carbon::parse('2026-07-08 12:14:00'),
        ])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:03:00', false);
    }
}
