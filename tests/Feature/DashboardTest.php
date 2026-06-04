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

    public function test_dashboard_renders_average_duration_for_terminal_scans(): void
    {
        $user = User::factory()->create();

        $this->createTerminalScan($user, Scan::STATUS_COMPLETED, 60);
        $this->createTerminalScan($user, Scan::STATUS_FAILED, 120);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:01:30');
    }

    private function createTerminalScan(User $user, string $status, int $durationSeconds): void
    {
        $startedAt = now()->subMinutes(10);

        $scan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => $status,
            'progress' => 100,
            'phase' => $status === Scan::STATUS_COMPLETED ? Scan::PHASE_COMPLETED : Scan::PHASE_FAILED,
        ]);
        $scan->created_at = $startedAt;
        $scan->updated_at = $startedAt->copy()->addSeconds($durationSeconds);
        $scan->completed_at = $startedAt->copy()->addSeconds($durationSeconds);
        $scan->save();
    }
}
