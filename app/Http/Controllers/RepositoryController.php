<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller for repository management.
 */
class RepositoryController extends BaseController
{

    /**
     * Check if the current user has admin privileges.
     *
     * @return bool True if user is admin, false otherwise
     */
    protected function isAdmin(): bool
    {
        return Auth::user()->is_admin;
    }

    /**
     * Ensure the user has admin privileges or return an error response.
     *
     * @return JsonResponse|null Error response if not admin, null if admin
     */
    protected function ensureAdmin(): ?JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->errorResponse('Access denied. Admin privileges required.', 403);
        }

        return null;
    }

    /**
     * List all repositories.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $query = Repository::select(
                'repositories.id',
                'repositories.name as repository_name',
                'repositories.access_url',
                'projects.name as project_name'
            )
            ->leftJoin('projects', 'projects.id', '=', 'repositories.project_id');

            // Filter repositories by user access if not admin
            if (!$this->isAdmin()) {
                $query->leftJoin('user_repositories', 'user_repositories.repository_id', '=', 'repositories.id')
                      ->where('user_repositories.user_id', Auth::id());
            }

            $repositories = $query->get();

            return $this->successResponse($repositories);
        } catch (Exception $e) {
            return $this->errorResponse("Failed to retrieve repositories: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Create a new repository.
     *
     * @param Request $request HTTP request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Check admin privileges
        $adminCheck = $this->ensureAdmin();
        if ($adminCheck) {
            return $adminCheck;
        }

        try {
            // Validate request
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'repository_name' => 'required|string|max:255',
                'repo_path' => 'required|string|max:255',
                'access_url' => 'nullable|string',
                'code_base_type' => 'nullable|string|in:Laravel,NodeJS,React/Vue',
                'has_dist_folder' => 'nullable|boolean'
            ]);

            // Create repository
            $repository = Repository::create([
                'project_id' => $validated['project_id'],
                'name' => $validated['repository_name'],
                'repo_path' => $validated['repo_path'],
                'access_url' => $validated['access_url'] ?? null,
                'code_base_type' => $validated['code_base_type'] ?? 'Laravel',
                'has_dist_folder' => $validated['has_dist_folder'] ?? false,
            ]);

            return $this->successResponse($repository, 'Repository created successfully', 201);
        } catch (Exception $e) {
            return $this->errorResponse("Failed to create repository: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Get a specific repository.
     *
     * @param int $id Repository ID
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $repository = Repository::with('project')->findOrFail($id);
            return $this->successResponse($repository);
        } catch (Exception $e) {
            return $this->errorResponse("Repository not found: {$e->getMessage()}", 404, $e);
        }
    }

    /**
     * Update a repository.
     *
     * @param Request $request HTTP request
     * @param int $id Repository ID
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Check admin privileges
        $adminCheck = $this->ensureAdmin();
        if ($adminCheck) {
            return $adminCheck;
        }

        try {
            // Validate request
            $validated = $request->validate([
                'project_id' => 'sometimes|required|exists:projects,id',
                'repository_name' => 'sometimes|required|string|max:255',
                'repo_path' => 'sometimes|required|string|max:255',
                'access_url' => 'nullable|string',
                'code_base_type' => 'nullable|string|in:Laravel,NodeJS,React/Vue',
                'has_dist_folder' => 'nullable|boolean'
            ]);

            // Find repository
            $repository = Repository::findOrFail($id);

            // Update repository
            $repository->update([
                'project_id' => $validated['project_id'] ?? $repository->project_id,
                'name' => $validated['repository_name'] ?? $repository->name,
                'repo_path' => $validated['repo_path'] ?? $repository->repo_path,
                'access_url' => $validated['access_url'] ?? $repository->access_url,
                'code_base_type' => $validated['code_base_type'] ?? $repository->code_base_type,
                'has_dist_folder' => $validated['has_dist_folder'] ?? $repository->has_dist_folder,
            ]);

            return $this->successResponse($repository, 'Repository updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse("Failed to update repository: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Delete a repository.
     *
     * @param int $id Repository ID
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // Check admin privileges
        $adminCheck = $this->ensureAdmin();
        if ($adminCheck) {
            return $adminCheck;
        }

        try {
            // Find repository
            $repository = Repository::findOrFail($id);

            // Delete repository
            $repository->delete();

            return $this->successResponse(null, 'Repository deleted successfully', 204);
        } catch (Exception $e) {
            return $this->errorResponse("Failed to delete repository: {$e->getMessage()}", 500, $e);
        }
    }
}
