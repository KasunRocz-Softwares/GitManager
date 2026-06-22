<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('view_projects')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $query = Project::with('repositories');

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('host', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== 'all') {
            $status = $request->input('is_active') === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $paginate = $request->input('paginate') ?? $_GET['paginate'] ?? null;
        if ($paginate === 'true') {
            $perPage = $request->input('per_page') ?? $_GET['per_page'] ?? 10;
            $page = $request->input('page') ?? $_GET['page'] ?? 1;
            return response()->json($query->paginate($perPage, ['*'], 'page', $page));
        }
        $projects = $query->get();
        return response()->json($projects);
    }

    public function store(Request $request)
    {
        if (!$request->user()->can('create_projects')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'host' => 'required|ip',
        ]);

        $project = Project::create($validated);

        return response()->json($project, 201);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->can('view_projects')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $project = Project::with('repositories')->findOrFail($id);
        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->can('edit_projects')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|required|string|max:255',
            'host' => 'sometimes|required|ip',
        ]);

        $project = Project::findOrFail($id);
        $project->update($validated);

        return response()->json($project);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('delete_projects')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json(null, 204);
    }

    public function toggleProjectStatus(Request $request, $id)
    {
        if (!$request->user()->can('edit_projects')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);
        $project = Project::findOrFail($id);
        $project->is_active = $validated['is_active'];
        $project->save();

        return response()->json([
            "success" => true,
            "message" => "Project status updated successfully",
        ]);
    }
}
