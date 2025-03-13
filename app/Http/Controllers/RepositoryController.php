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
            $repositories = Repository::select('repositories.id', 'repositories.name as repository_name', 'repositories.access_url', 'projects.name as project_name')
            ->leftJoin('user_repositories', 'user_repositories.repository_id', '=', 'repositories.id')
            ->leftJoin('projects', 'projects.id', '=', 'repositories.project_id')
            ->where('user_repositories.user_id', Auth::user()->id)
            ->get();
            return response()->json($repositories);
        }
        $repositories = Repository::select('repositories.id', 'repositories.name as repository_name', 'repositories.access_url', 'projects.name as project_name')
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
            'name' => 'required|string|max:255',
            'repo_path' => 'required|string|max:255',
            'access_url'=> 'nullable|string'
        ]);

        $repository = Repository::create($validated);

        return response()->json($repository, 201);
    }

    public function show($id)
    {
        $repository = Repository::with('project')->findOrFail($id);
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
            'name' => 'sometimes|required|string|max:255',
            'repo_path' => 'sometimes|required|string|max:255',
        ]);

        $repository = Repository::findOrFail($id);
        $repository->update($validated);

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
}
