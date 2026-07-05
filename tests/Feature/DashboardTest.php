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

    public function test_dashboard_includes_progress_polling_hooks(): void
    {
        $user = User::factory()->create();

        $this->createScan($user, Scan::STATUS_RUNNING, null);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-poll-url', false);
        $response->assertSee('data-scan-progress-bar', false);
        $response->assertSee('data-scan-status-badge', false);
    }

    public function test_dashboard_calculates_average_terminal_scan_duration(): void
    {
        $user = User::factory()->create();

        $this->createScan($user, Scan::STATUS_COMPLETED, 120);
        $this->createScan($user, Scan::STATUS_FAILED, 240);
        $this->createScan($user, Scan::STATUS_RUNNING, null);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats): bool => $stats['avg_duration_seconds'] === 180);
        $response->assertSee('00:03:00');
    }

    private function createScan(User $user, string $status, ?int $durationSeconds): Scan
    {
        $createdAt = now()->subHour();

        $scan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => $status,
            'progress' => $status === Scan::STATUS_RUNNING ? 50 : 100,
            'phase' => $status === Scan::STATUS_RUNNING ? Scan::PHASE_ZAP_ACTIVE : Scan::PHASE_COMPLETED,
        ]);
        $scan->created_at = $createdAt;
        $scan->updated_at = $createdAt;
        $scan->completed_at = $durationSeconds === null ? null : $createdAt->copy()->addSeconds($durationSeconds);
        $scan->save();

        return $scan;
    }
}
