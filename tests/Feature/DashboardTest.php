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

    public function test_dashboard_includes_interactive_hooks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-risk-toggle="pie"', false);
        $response->assertSee('data-risk-hover', false);
    }

    public function test_dashboard_displays_average_scan_duration_on_sqlite(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-05-29 12:00:00'));

        try {
            Scan::query()->create([
                'user_id' => $user->id,
                'url' => 'https://example.com',
                'status' => Scan::STATUS_COMPLETED,
                'progress' => 100,
                'phase' => Scan::PHASE_COMPLETED,
                'completed_at' => now()->addMinutes(2),
            ]);

            $response = $this->actingAs($user)->get(route('dashboard'));
        } finally {
            Carbon::setTestNow();
        }

        $response->assertOk();
        $response->assertSee('00:02:00', false);
    }
}
