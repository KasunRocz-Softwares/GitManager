<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $user;
    private $project;
    private $repo1;
    private $repo2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // Create regular user
        $this->user = User::factory()->create([
            'is_admin' => false,
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        // Create project and repositories
        $this->project = Project::create([
            'name' => 'Test Project',
            'username' => 'testuser',
            'password' => 'testpass',
            'host' => '127.0.0.1',
        ]);

        $this->repo1 = Repository::create([
            'project_id' => $this->project->id,
            'name' => 'Repo 1',
            'repo_path' => '/path/to/repo1',
        ]);

        $this->repo2 = Repository::create([
            'project_id' => $this->project->id,
            'name' => 'Repo 2',
            'repo_path' => '/path/to/repo2',
        ]);

        // Assign repo1 to user initially
        $this->user->repositories()->sync([$this->repo1->id]);
    }

    public function test_non_admin_cannot_update_user(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/users/{$this->user->id}", [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user_profile_without_altering_repositories(): void
    {
        // Assert user has repo1 initially
        $this->assertEquals([$this->repo1->id], $this->user->repositories->pluck('id')->toArray());

        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/users/{$this->user->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Reload user and verify profile updated
        $this->user->refresh();
        $this->assertEquals('Updated Name', $this->user->name);
        $this->assertEquals('updated@example.com', $this->user->email);

        // Verify repository assignment remains unchanged (repo1 is still assigned)
        $this->assertEquals([$this->repo1->id], $this->user->repositories->pluck('id')->toArray());
    }

    public function test_admin_can_update_user_repositories(): void
    {
        // Assert user has repo1 initially
        $this->assertEquals([$this->repo1->id], $this->user->repositories->pluck('id')->toArray());

        // Update repository assignment to repo2 only
        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/users/{$this->user->id}", [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'repository_ids' => [$this->repo2->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Reload user and verify repository assignments were updated
        $this->user->refresh();
        $this->assertEquals([$this->repo2->id], $this->user->repositories->pluck('id')->toArray());
    }

    public function test_admin_can_clear_user_repositories_by_passing_empty_array(): void
    {
        // Assert user has repo1 initially
        $this->assertEquals([$this->repo1->id], $this->user->repositories->pluck('id')->toArray());

        // Clear repository assignments
        $response = $this->actingAs($this->admin, 'api')
            ->putJson("/api/users/{$this->user->id}", [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'repository_ids' => [],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Reload user and verify repository assignments are empty
        $this->user->refresh();
        $this->assertEmpty($this->user->repositories);
    }
}
