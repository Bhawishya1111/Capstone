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

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('dashboard-', false);
    }

    public function test_dashboard_displays_average_terminal_scan_duration(): void
    {
        $user = User::factory()->create();
        $startedAt = Carbon::parse('2026-07-11 12:00:00');

        Scan::create([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_COMPLETED,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
            'completed_at' => $startedAt->copy()->addSeconds(120),
        ]);

        Scan::create([
            'user_id' => $user->id,
            'url' => 'https://example.org',
            'status' => Scan::STATUS_FAILED,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
            'completed_at' => $startedAt->copy()->addSeconds(240),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:03:00');
    }
}
