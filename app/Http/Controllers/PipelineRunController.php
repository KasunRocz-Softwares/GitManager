<?php

namespace App\Http\Controllers;

use App\Jobs\RunPipelineJob;
use App\Models\PipelineRun;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class PipelineRunController extends Controller
{
    /**
     * Trigger a new pipeline execution for a repository.
     */
    public function runPipeline(Request $request, $repoId)
    {
        if (!$request->user()->can('run_git_commands')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $repository = Repository::with('pipeline')->findOrFail($repoId);

        // Access Control Check
        if (!Auth::user()->hasAnyRole(['Super Admin', 'Admin'])) {
            $hasAccess = $repository->users()->where('users.id', Auth::user()->id)->exists();
            if (!$hasAccess || !$repository->is_active) {
                return response()->json([
                    "success" => false,
                    "message" => "Access denied"
                ], 403);
            }
        }

        $pipeline = $repository->pipeline;
        if (!$pipeline) {
            return response()->json([
                "success" => false,
                "message" => "This repository has no pipeline assigned."
            ], 400);
        }

        $stages = $pipeline->stages ?? [];
        if (empty($stages)) {
            return response()->json([
                "success" => false,
                "message" => "The assigned pipeline has no stages defined."
            ], 400);
        }

        // Validate variables if any stage has_input
        $variables = $request->input('variables', []);
        foreach ($stages as $stage) {
            if (!empty($stage['has_input']) && !empty($stage['input_key'])) {
                $key = $stage['input_key'];
                if (!isset($variables[$key]) || trim($variables[$key]) === '') {
                    return response()->json([
                        "success" => false,
                        "message" => "Missing required parameter: " . ($stage['input_label'] ?? $key)
                    ], 422);
                }
            }
        }

        // Initialize stage statuses
        $stageStatuses = [];
        foreach ($stages as $idx => $stage) {
            $stageStatuses[$idx] = 'pending';
        }

        // Create the PipelineRun record
        $run = PipelineRun::create([
            'repository_id' => $repository->id,
            'pipeline_id' => $pipeline->id,
            'user_id' => Auth::id(),
            'status' => 'pending',
            'stage_statuses' => $stageStatuses,
            'variables' => $variables,
            'logs' => "[SYSTEM] Pipeline execution queued...\n"
        ]);

        // Dispatch background job
        RunPipelineJob::dispatch($run->id);

        return response()->json($run, 201);
    }

    /**
     * Show the status and logs of a pipeline run.
     */
    public function show(Request $request, $id)
    {
        $run = PipelineRun::with('repository')->findOrFail($id);

        // Access Control Check
        if (!Auth::user()->hasAnyRole(['Super Admin', 'Admin'])) {
            $hasAccess = $run->repository->users()->where('users.id', Auth::user()->id)->exists();
            if (!$hasAccess || !$run->repository->is_active) {
                return response()->json([
                    "success" => false,
                    "message" => "Access denied"
                ], 403);
            }
        }

        // Fetch logs from Redis if currently running, otherwise from Database
        $redisKey = 'pipeline_run:' . $id . ':logs';
        $redisLogs = Redis::get($redisKey);
        if ($redisLogs !== null) {
            $run->logs = $redisLogs;
        }

        return response()->json($run);
    }

    /**
     * Fetch the latest pipeline run for a repository.
     */
    public function latestRun(Request $request, $repoId)
    {
        $repository = Repository::findOrFail($repoId);

        // Access Control Check
        if (!Auth::user()->hasAnyRole(['Super Admin', 'Admin'])) {
            $hasAccess = $repository->users()->where('users.id', Auth::user()->id)->exists();
            if (!$hasAccess || !$repository->is_active) {
                return response()->json([
                    "success" => false,
                    "message" => "Access denied"
                ], 403);
            }
        }

        $run = PipelineRun::where('repository_id', $repoId)->latest()->first();

        if ($run) {
            // Check Redis logs for the active run
            $redisKey = 'pipeline_run:' . $run->id . ':logs';
            $redisLogs = Redis::get($redisKey);
            if ($redisLogs !== null) {
                $run->logs = $redisLogs;
            }
        }

        return response()->json($run);
    }
}
