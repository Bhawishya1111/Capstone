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

    public function test_dashboard_renders_terminal_scan_average_duration(): void
    {
        $user = User::factory()->create();

        Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_COMPLETED,
            'progress' => 100,
            'phase' => Scan::PHASE_COMPLETED,
            'created_at' => Carbon::parse('2026-07-08 12:00:00'),
            'updated_at' => Carbon::parse('2026-07-08 12:02:00'),
            'completed_at' => Carbon::parse('2026-07-08 12:02:00'),
        ]);

        Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.org',
            'status' => Scan::STATUS_FAILED,
            'progress' => 100,
            'phase' => Scan::PHASE_FAILED,
            'created_at' => Carbon::parse('2026-07-08 12:10:00'),
            'updated_at' => Carbon::parse('2026-07-08 12:14:00'),
            'completed_at' => Carbon::parse('2026-07-08 12:14:00'),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('00:03:00', false);
    }
}
