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

        $this->createScanWithDuration($user, Scan::STATUS_COMPLETED, 60);
        $this->createScanWithDuration($user, Scan::STATUS_FAILED, 180);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00');
    }

    private function createScanWithDuration(User $user, string $status, int $durationSeconds): void
    {
        $startedAt = now()->subMinutes(10);

        $scan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => $status,
            'progress' => 100,
            'phase' => $status === Scan::STATUS_COMPLETED ? Scan::PHASE_COMPLETED : Scan::PHASE_FAILED,
            'completed_at' => $startedAt->copy()->addSeconds($durationSeconds),
        ]);

        $scan->created_at = $startedAt;
        $scan->save();
    }
}
