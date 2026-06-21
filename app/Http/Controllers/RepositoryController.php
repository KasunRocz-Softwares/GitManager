<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepositoryController extends Controller
{
    public function index()
    {
        if (!Auth::user()->is_admin) {
            $repositories = Repository::select('repositories.id', 'repositories.name as repository_name', 'repositories.access_url', 'repositories.project_id', 'repositories.is_active', 'projects.name as project_name')
            ->leftJoin('user_repositories', 'user_repositories.repository_id', '=', 'repositories.id')
            ->leftJoin('projects', 'projects.id', '=', 'repositories.project_id')
            ->where('user_repositories.user_id', Auth::user()->id)
            ->where('repositories.is_active', 1)
            ->get();
            return response()->json($repositories);
        }
        $repositories = Repository::select('repositories.id', 'repositories.name as repository_name', 'repositories.access_url', 'repositories.project_id', 'repositories.is_active', 'projects.name as project_name')
        ->leftJoin('projects', 'projects.id', '=', 'repositories.project_id')
        ->get();

        return response()->json($repositories);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->is_admin) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'repository_name' => 'required|string|max:255',
            'repo_path' => 'required|string|max:255',
            'access_url'=> 'nullable|string'
        ]);

        $repository = Repository::create([
            'project_id' => $validated['project_id'],
            'name' => $validated['repository_name'],
            'repo_path'=> $validated['repo_path'],
            'access_url' => $validated['access_url'],
        ]);

        return response()->json($repository, 201);
    }

    public function show($id)
    {
        $repository = Repository::with('project')->findOrFail($id);
        if (!Auth::user()->is_admin) {
            $hasAccess = $repository->users()->where('users.id', Auth::user()->id)->exists();
            if (!$hasAccess || !$repository->is_active) {
                return response()->json([
                    "success" => false,
                    "message" => "Access denied"
                ], 403);
            }
        }
        return response()->json($repository);
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->is_admin) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'project_id' => 'sometimes|required|exists:projects,id',
            'repository_name' => 'sometimes|required|string|max:255',
            'repo_path' => 'sometimes|required|string|max:255',
            'access_url'=> 'nullable|string'
        ]);

        $repository = Repository::findOrFail($id);

        $repository->update([
            'project_id' => $validated['project_id'],
            'name' => $validated['repository_name'],
            'repo_path'=> $validated['repo_path'],
            'access_url' => $validated['access_url'],
        ]);

        return response()->json($repository);
    }

    public function destroy($id)
    {
        if (!Auth::user()->is_admin) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }
        $repository = Repository::findOrFail($id);
        $repository->delete();

        return response()->json(null, 204);
    }

    public function toggleRepositoryStatus(Request $request, $id)
    {
        if (!Auth::user()->is_admin) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);
        
        $repository = Repository::findOrFail($id);
        $repository->is_active = $validated['is_active'];
        $repository->save();

        return response()->json([
            "success" => true,
            "message" => "Repository status updated successfully",
        ]);
    }
}
