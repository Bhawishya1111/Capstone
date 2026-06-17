<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('WAVe-Lite', false);
    }

    public function test_dashboard_includes_progress_polling_hooks(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $scan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_RUNNING,
            'progress' => 10,
            'phase' => Scan::PHASE_STARTING,
        ]);
        $scan->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-scan-row', false);
        $response->assertSee('data-poll-url="'.route('scans.progress', $scan).'"', false);
    }

    public function test_dashboard_calculates_terminal_scan_average_duration_on_sqlite(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $baseTime = now()->startOfMinute();

        $completedScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://completed.example.com',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
            'completed_at' => $baseTime,
        ]);
        $completedScan->created_at = $baseTime->copy()->subMinutes(10);
        $completedScan->updated_at = $baseTime;
        $completedScan->save();

        $failedScan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://failed.example.com',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
            'completed_at' => $baseTime,
        ]);
        $failedScan->created_at = $baseTime->copy()->subMinutes(20);
        $failedScan->updated_at = $baseTime;
        $failedScan->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:15:00', false);
    }
}
