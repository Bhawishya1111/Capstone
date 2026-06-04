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
        $this->createScan($user, Scan::STATUS_RUNNING, 10);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-scan-progress-bar', false);
        $response->assertSee('data-poll-url', false);
    }

    public function test_dashboard_renders_average_duration_for_terminal_scans(): void
    {
        $user = User::factory()->create();

        $this->createScan($user, Scan::STATUS_COMPLETED, 60);
        $this->createScan($user, Scan::STATUS_FAILED, 120);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:01:30');
    }

    private function createScan(User $user, string $status, int $durationSeconds): void
    {
        $startedAt = now()->subMinutes(10);

        $scan = new Scan([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => $status,
            'progress' => $status === Scan::STATUS_RUNNING ? 25 : 100,
            'phase' => match ($status) {
                Scan::STATUS_COMPLETED => Scan::PHASE_COMPLETED,
                Scan::STATUS_RUNNING => Scan::PHASE_ZAP_SPIDER,
                default => Scan::PHASE_FAILED,
            },
        ]);
        $scan->created_at = $startedAt;
        $scan->updated_at = $startedAt->copy()->addSeconds($durationSeconds);
        if ($status !== Scan::STATUS_RUNNING) {
            $scan->completed_at = $startedAt->copy()->addSeconds($durationSeconds);
        }
        $scan->save();
    }
}
