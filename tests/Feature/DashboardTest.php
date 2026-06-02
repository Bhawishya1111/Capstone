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
        $completedAt = now()->startOfSecond();

        Scan::unguarded(function () use ($user, $completedAt): void {
            Scan::query()->create([
                'user_id' => $user->id,
                'url' => 'https://example.com',
                'status' => Scan::STATUS_COMPLETED,
                'progress' => 100,
                'phase' => Scan::PHASE_COMPLETED,
                'created_at' => $completedAt->copy()->subMinutes(3),
                'updated_at' => $completedAt->copy()->subMinutes(3),
                'completed_at' => $completedAt,
            ]);

            Scan::query()->create([
                'user_id' => $user->id,
                'url' => 'https://example.org',
                'status' => Scan::STATUS_FAILED,
                'progress' => 100,
                'phase' => Scan::PHASE_FAILED,
                'created_at' => $completedAt->copy()->subMinute(),
                'updated_at' => $completedAt->copy()->subMinute(),
                'completed_at' => $completedAt,
            ]);
        });

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:02:00', false);
    }
}
