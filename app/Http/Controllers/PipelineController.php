<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('view_pipelines')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $query = Pipeline::query();

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginate = $request->input('paginate') ?? $_GET['paginate'] ?? null;
        if ($paginate === 'true') {
            $perPage = $request->input('per_page') ?? $_GET['per_page'] ?? 10;
            $page = $request->input('page') ?? $_GET['page'] ?? 1;
            return response()->json($query->paginate($perPage, ['*'], 'page', $page));
        }

        $pipelines = $query->get();
        return response()->json($pipelines);
    }

    public function store(Request $request)
    {
        if (!$request->user()->can('create_pipelines')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stages' => 'required|array',
            'stages.*.name' => 'required|string',
            'stages.*.has_input' => 'required|boolean',
            'stages.*.input_label' => 'nullable|required_if:stages.*.has_input,true|string',
            'stages.*.input_placeholder' => 'nullable|string',
            'stages.*.input_key' => 'nullable|required_if:stages.*.has_input,true|string',
            'stages.*.commands' => 'required|array',
            'stages.*.commands.*' => 'required|string',
        ]);

        $pipeline = Pipeline::create($validated);

        return response()->json($pipeline, 201);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->can('view_pipelines')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $pipeline = Pipeline::findOrFail($id);
        return response()->json($pipeline);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->can('edit_pipelines')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'stages' => 'sometimes|required|array',
            'stages.*.name' => 'sometimes|required|string',
            'stages.*.has_input' => 'sometimes|required|boolean',
            'stages.*.input_label' => 'nullable|required_if:stages.*.has_input,true|string',
            'stages.*.input_placeholder' => 'nullable|string',
            'stages.*.input_key' => 'nullable|required_if:stages.*.has_input,true|string',
            'stages.*.commands' => 'sometimes|required|array',
            'stages.*.commands.*' => 'sometimes|required|string',
        ]);

        $pipeline = Pipeline::findOrFail($id);
        $pipeline->update($validated);

        return response()->json($pipeline);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('delete_pipelines')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $pipeline = Pipeline::findOrFail($id);
        $pipeline->delete();

        return response()->json(null, 204);
    }
}
