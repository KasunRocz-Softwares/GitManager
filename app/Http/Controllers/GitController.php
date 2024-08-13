<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Models\Repository;
use App\Services\GitService;
use Illuminate\Http\Request;

#[AllowDynamicProperties] class GitController extends Controller
{
    public function __construct()
    {
        $this->gitService = null;
    }

    protected function initializeGitService($repoId): void
    {
        $repository = Repository::with('project')->findOrFail($repoId);
        $project = $repository->project;

        $sshHost = $project->host;
        $sshUsername = $project->username;
        $sshPassword = $project->password;
        $repoPath = $repository->repo_path;

        $this->gitService = new GitService($sshHost, $sshUsername, $sshPassword, $repoPath);
    }

    public function listBranches($repoId): \Illuminate\Http\JsonResponse
    {
        $this->initializeGitService($repoId);

        try {
            $branches = $this->gitService->listBranches();
            return response()->json($branches, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkoutBranch(Request $request, $repoId): \Illuminate\Http\JsonResponse
    {
        $this->initializeGitService($repoId);
        $branchName = $request->input('branch_name');

        try {
            $checkout_branch = $this->gitService->checkoutBranch($branchName);
            return response()->json(['message' => 'Branch checked out successfully.','branch' => $checkout_branch], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function runMultipleCommands(Request $request, $repoId)
    {
        $this->initializeGitService($repoId);
        $commands = $request->input('commands');

        if (!is_array($commands)) {
            return response()->json(['error' => 'Commands should be an array.'], 400);
        }

        try {
            $output = $this->gitService->runMultipleCommands($commands);
            return response()->json(['message' => $output], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function currentBranch($repoId): \Illuminate\Http\JsonResponse
    {
        $this->initializeGitService($repoId);
        try {
            $branches = $this->gitService->currentBranch();
            return response()->json(['currentBranch'=>$branches], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
