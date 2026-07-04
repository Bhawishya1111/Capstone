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
            'progress' => 42,
            'phase' => Scan::PHASE_ZAP_SPIDER,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-poll-url', false);
        $response->assertSee('data-scan-progress-bar', false);
    }

    public function test_dashboard_averages_terminal_scan_duration_on_sqlite(): void
    {
        $user = User::factory()->create();

        $this->createScanWithDuration($user, Scan::STATUS_COMPLETED, 60);
        $this->createScanWithDuration($user, Scan::STATUS_FAILED, 180);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00', false);
    }

    private function createScanWithDuration(User $user, string $status, int $seconds): void
    {
        $createdAt = Carbon::parse('2026-07-04 12:00:00');

        $scan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => $status,
            'progress' => 100,
            'phase' => $status === Scan::STATUS_COMPLETED ? Scan::PHASE_COMPLETED : Scan::PHASE_FAILED,
        ]);
        $scan->created_at = $createdAt;
        $scan->completed_at = $createdAt->copy()->addSeconds($seconds);
        $scan->save();
    }
}
