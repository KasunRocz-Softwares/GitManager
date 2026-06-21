<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Repository;
use App\Models\RepoActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $user;
    private $project;
    private $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->user = User::factory()->create([
            'is_admin' => false,
        ]);

        // Create project and repository
        $this->project = Project::create([
            'name' => 'Test Project',
            'username' => 'testuser',
            'password' => 'testpass',
            'host' => '127.0.0.1',
        ]);

        $this->repo = Repository::create([
            'project_id' => $this->project->id,
            'name' => 'Test Repo',
            'repo_path' => '/path/to/repo',
        ]);
    }

    public function test_activity_chart_requires_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/activity-chart');
        $response->assertStatus(401);
    }

    public function test_activity_chart_returns_admin_all_users_logs_by_default(): void
    {
        // Create logs for user and admin
        $log1 = new RepoActivityLog([
            'user_id' => $this->user->id,
            'repository_id' => $this->repo->id,
            'type' => 'commit',
        ]);
        $log1->created_at = Carbon::now()->subDays(2);
        $log1->save();

        $log2 = new RepoActivityLog([
            'user_id' => $this->admin->id,
            'repository_id' => $this->repo->id,
            'type' => 'push',
        ]);
        $log2->created_at = Carbon::now()->subDays(1);
        $log2->save();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/dashboard/activity-chart');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_activity_chart_returns_only_own_logs_for_regular_user(): void
    {
        // Create logs for user and admin
        $log1 = new RepoActivityLog([
            'user_id' => $this->user->id,
            'repository_id' => $this->repo->id,
            'type' => 'commit',
        ]);
        $log1->created_at = Carbon::now()->subDays(2);
        $log1->save();

        $log2 = new RepoActivityLog([
            'user_id' => $this->admin->id,
            'repository_id' => $this->repo->id,
            'type' => 'push',
        ]);
        $log2->created_at = Carbon::now()->subDays(1);
        $log2->save();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/dashboard/activity-chart');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Regular user should only see their own logs
        $this->assertCount(1, $data);
        $this->assertEquals($this->user->id, $data[0]['user_id']);
    }

    public function test_activity_chart_filters_by_user_for_admin(): void
    {
        // Create logs for user and admin
        $log1 = new RepoActivityLog([
            'user_id' => $this->user->id,
            'repository_id' => $this->repo->id,
            'type' => 'commit',
        ]);
        $log1->created_at = Carbon::now()->subDays(2);
        $log1->save();

        $log2 = new RepoActivityLog([
            'user_id' => $this->admin->id,
            'repository_id' => $this->repo->id,
            'type' => 'push',
        ]);
        $log2->created_at = Carbon::now()->subDays(1);
        $log2->save();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/dashboard/activity-chart?user_id=' . $this->user->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals($this->user->id, $data[0]['user_id']);
    }

    public function test_activity_chart_filters_by_date_range(): void
    {
        // Create logs on different dates
        $log1 = new RepoActivityLog([
            'user_id' => $this->user->id,
            'repository_id' => $this->repo->id,
            'type' => 'commit',
        ]);
        $log1->created_at = Carbon::now()->subDays(10);
        $log1->save();

        $log2 = new RepoActivityLog([
            'user_id' => $this->user->id,
            'repository_id' => $this->repo->id,
            'type' => 'push',
        ]);
        $log2->created_at = Carbon::now()->subDays(5);
        $log2->save();

        $log3 = new RepoActivityLog([
            'user_id' => $this->user->id,
            'repository_id' => $this->repo->id,
            'type' => 'push',
        ]);
        $log3->created_at = Carbon::now()->subDays(1);
        $log3->save();

        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->subDays(3)->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/dashboard/activity-chart?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Only the log from 5 days ago should match the range
        $this->assertCount(1, $data);
        $this->assertEquals(Carbon::now()->subDays(5)->format('Y-m-d'), $data[0]['date']);
    }
}
