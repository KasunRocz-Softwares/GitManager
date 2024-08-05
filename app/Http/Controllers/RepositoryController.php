<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function index()
    {
        $repositories = Repository::with('project')->get();
        return response()->json($repositories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'repo_path' => 'required|string|max:255',
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
        $repository = Repository::findOrFail($id);
        $repository->delete();

        return response()->json(null, 204);
    }
}
