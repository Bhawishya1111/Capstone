<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_scan_progress_works_without_jobs_table(): void
    {
        $user = User::factory()->create();
        $scan = Scan::query()->create([
            'user_id' => $user->id,
            'url' => 'https://example.com',
            'status' => Scan::STATUS_QUEUED,
            'progress' => 0,
            'phase' => Scan::PHASE_STARTING,
        ]);

        Schema::dropIfExists('jobs');

        $response = $this->actingAs($user)->getJson(route('scans.progress', $scan));

        $response
            ->assertOk()
            ->assertJson([
                'id' => $scan->id,
                'status' => Scan::STATUS_QUEUED,
                'queue_position' => null,
                'queue_notice' => null,
            ]);
    }
}
