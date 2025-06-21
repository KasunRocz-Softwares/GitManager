<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with('repositories')->get();
        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'host' => 'required|ip',
        ]);

        $project = Project::create($validated);

        return response()->json($project, 201);
    }

    public function show($id)
    {
        $project = Project::with('repositories')->findOrFail($id);
        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
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

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json(null, 204);
    }

    public function toggleProjectStatus(Request $request, $id)
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
        $project = Project::findOrFail($id);
        $project->is_active = $validated['is_active'];
        $project->save();

        return response()->json([
            "success" => true,
            "message" => "Project status updated successfully",
        ]);
    }
}
