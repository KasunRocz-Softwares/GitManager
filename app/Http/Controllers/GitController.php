<?php

namespace App\Http\Controllers;

use App\Models\RepoActivityLog;
use App\Models\Repository;
use App\Services\GitService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Controller for Git operations.
 */
class GitController extends BaseController
{
    /**
     * The Git service instance.
     *
     * @var GitService|null
     */
    protected ?GitService $gitService = null;

    /**
     * Initialize the Git service for a repository.
     *
     * @param int $repoId Repository ID
     * @return Repository The repository instance
     * @throws Exception If repository or project not found
     */
    protected function initializeGitService(int $repoId): Repository
    {
        $repository = Repository::with('project')->findOrFail($repoId);
        $project = $repository->project;

        if (!$project) {
            throw new Exception("Project not found for repository ID: {$repoId}");
        }

        $this->gitService = new GitService(
            $project->host,
            $project->username,
            $project->password,
            $repository->repo_path
        );

        return $repository;
    }


    /**
     * List all branches in a repository.
     *
     * @param int $repoId Repository ID
     * @return JsonResponse
     */
    public function listBranches(int $repoId): JsonResponse
    {
        try {
            $this->initializeGitService($repoId);
            $branches = $this->gitService->listBranches();
            return $this->successResponse($branches);
        } catch (Exception $e) {
            return $this->errorResponse("Failed to list branches: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Checkout a branch in a repository.
     *
     * @param Request $request HTTP request
     * @param int $repoId Repository ID
     * @return JsonResponse
     */
    public function checkoutBranch(Request $request, int $repoId): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'branch_name' => 'required|string|max:100',
            ]);

            $this->initializeGitService($repoId);

            // Log the activity
            RepoActivityLog::makeRepoLogs(
                Auth::id(),
                $repoId,
                'git-checkout',
                $validated['branch_name']
            );

            $currentBranch = $this->gitService->checkoutBranch($validated['branch_name']);

            return $this->successResponse(
                ['branch' => $currentBranch],
                'Branch checked out successfully'
            );
        } catch (Exception $e) {
            return $this->errorResponse("Failed to checkout branch: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Run multiple Git commands on a repository.
     *
     * @param Request $request HTTP request
     * @param int $repoId Repository ID
     * @return JsonResponse
     */
    public function runMultipleCommands(Request $request, int $repoId): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'commands' => 'required|array',
                'commands.*' => 'required|string',
            ]);

            $this->initializeGitService($repoId);

            // Log the activity
            RepoActivityLog::makeRepoLogs(
                Auth::id(),
                $repoId,
                'run-command',
                json_encode($validated['commands'])
            );

            // Check commands for security risks
            foreach ($validated['commands'] as $command) {
                $securityCheckResult = $this->checkCommandSecurity($command);
                if (!$securityCheckResult['safe']) {
                    return $this->errorResponse(
                        $securityCheckResult['reason'],
                        400
                    );
                }
            }

            $output = $this->gitService->runMultipleCommands($validated['commands']);
            return $this->successResponse(['output' => $output], 'Commands executed successfully');
        } catch (Exception $e) {
            return $this->errorResponse("Failed to run commands: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Check if a command is safe to execute.
     *
     * @param string $command Command to check
     * @return array Array with 'safe' boolean and 'reason' string if unsafe
     */
    protected function checkCommandSecurity(string $command): array
    {
        try {
            $response = Http::withToken(env('AI_ACCESS_TOKEN'))
                ->post(env('AI_BASE_URL') . '/git_manager_guard', [
                    'command' => $command,
                ]);

            $aiResult = $response->json();

            if (!empty($aiResult['data']['is_risk']) && $aiResult['data']['is_risk'] === true) {
                return [
                    'safe' => false,
                    'reason' => $aiResult['data']['reason'] ?? 'Command flagged as potentially unsafe'
                ];
            }

            return ['safe' => true];
        } catch (Exception $e) {
            Log::warning("Security check failed: {$e->getMessage()}");
            // If security check fails, we err on the side of caution
            return [
                'safe' => false,
                'reason' => 'Security check service unavailable, command rejected'
            ];
        }
    }

    /**
     * Get the current branch of a repository.
     *
     * @param int $repoId Repository ID
     * @return JsonResponse
     */
    public function currentBranch(int $repoId): JsonResponse
    {
        try {
            $this->initializeGitService($repoId);
            $currentBranch = $this->gitService->currentBranch();
            return $this->successResponse(['currentBranch' => $currentBranch]);
        } catch (Exception $e) {
            return $this->errorResponse("Failed to get current branch: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Upload and deploy a dist folder to a repository.
     *
     * @param Request $request HTTP request
     * @param int $repoId Repository ID
     * @return JsonResponse
     */
    public function uploadDistFolder(Request $request, int $repoId): JsonResponse
    {
        $tempDir = null;

        try {
            // Get repository and check if dist folder is enabled
            $repository = $this->initializeGitService($repoId);

            if (!$repository->has_dist_folder) {
                return $this->errorResponse(
                    'This repository does not support dist folder uploads',
                    400
                );
            }

            // Validate the request
            $validated = $request->validate([
                'dist_folder' => 'required|file|mimes:zip,rar,tar,gz|max:50000', // 50MB max
                'commit_message' => 'required|string|max:255',
                'branch' => 'required|string|max:100',
            ]);

            // Create temporary directory
            $tempDir = $this->createTempDirectory();

            // Extract the uploaded file
            $extractPath = $this->extractUploadedFile(
                $request->file('dist_folder'),
                $tempDir
            );

            // Log the activity
            RepoActivityLog::makeRepoLogs(
                Auth::id(),
                $repoId,
                'upload-dist',
                $validated['commit_message']
            );

            // Run git commands to push the dist folder
            $commands = $this->buildDistFolderCommands(
                $validated['branch'],
                $extractPath,
                $validated['commit_message']
            );

            $output = $this->gitService->runMultipleCommands($commands);

            // Clean up temporary files
            $this->cleanupTempDirectory($tempDir);

            return $this->successResponse(
                ['output' => $output],
                'Dist folder uploaded and pushed successfully'
            );
        } catch (Exception $e) {
            // Clean up temporary files if they exist
            if ($tempDir && file_exists($tempDir)) {
                $this->cleanupTempDirectory($tempDir);
            }

            return $this->errorResponse("Failed to upload dist folder: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Create a temporary directory for file operations.
     *
     * @return string Path to the temporary directory
     * @throws Exception If directory creation fails
     */
    protected function createTempDirectory(): string
    {
        $tempDir = storage_path('app/temp/' . uniqid('dist_', true));

        if (!file_exists($tempDir) && !mkdir($tempDir, 0777, true)) {
            throw new Exception("Failed to create temporary directory: {$tempDir}");
        }

        return $tempDir;
    }

    /**
     * Extract an uploaded file to a temporary directory.
     *
     * @param \Illuminate\Http\UploadedFile $file Uploaded file
     * @param string $tempDir Temporary directory path
     * @return string Path to the extracted files
     * @throws Exception If extraction fails
     */
    protected function extractUploadedFile($file, string $tempDir): string
    {
        $fileName = $file->getClientOriginalName();
        $file->move($tempDir, $fileName);

        $extractPath = $tempDir . '/extracted';
        if (!mkdir($extractPath, 0777, true)) {
            throw new Exception("Failed to create extraction directory: {$extractPath}");
        }

        $filePath = $tempDir . '/' . $fileName;
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (strtolower($extension) === 'zip') {
            $this->extractZipFile($filePath, $extractPath);
        } else {
            $this->extractArchiveFile($filePath, $extractPath, $extension);
        }

        return $extractPath;
    }

    /**
     * Extract a ZIP file.
     *
     * @param string $filePath Path to the ZIP file
     * @param string $extractPath Path to extract to
     * @throws Exception If extraction fails
     */
    protected function extractZipFile(string $filePath, string $extractPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            throw new Exception('Failed to extract ZIP file');
        }
    }

    /**
     * Extract an archive file using system commands.
     *
     * @param string $filePath Path to the archive file
     * @param string $extractPath Path to extract to
     * @param string $extension File extension
     * @throws Exception If extraction fails
     */
    protected function extractArchiveFile(string $filePath, string $extractPath, string $extension): void
    {
        $command = match (strtolower($extension)) {
            'tar' => "tar -xf " . escapeshellarg($filePath) . " -C " . escapeshellarg($extractPath),
            'gz', 'tgz' => "tar -xzf " . escapeshellarg($filePath) . " -C " . escapeshellarg($extractPath),
            'rar' => "unrar x " . escapeshellarg($filePath) . " " . escapeshellarg($extractPath),
            default => throw new Exception('Unsupported archive format: ' . $extension),
        };

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception('Failed to extract archive: ' . implode("\n", $output));
        }
    }

    /**
     * Build Git commands to update the dist folder.
     *
     * @param string $branch Branch name
     * @param string $extractPath Path to extracted files
     * @param string $commitMessage Commit message
     * @return array Array of Git commands
     */
    protected function buildDistFolderCommands(string $branch, string $extractPath, string $commitMessage): array
    {
        return [
            "sudo git fetch",
            "sudo git checkout " . escapeshellarg($branch),
            "sudo git pull",
            "sudo rm -rf dist",  // Remove existing dist folder
            "sudo mkdir -p dist", // Create new dist folder
            "sudo cp -r " . escapeshellarg($extractPath) . "/* dist/", // Copy extracted files to dist folder
            "sudo git add dist",
            "sudo git commit -m " . escapeshellarg($commitMessage),
            "sudo git push origin " . escapeshellarg($branch)
        ];
    }

    /**
     * Clean up a temporary directory.
     *
     * @param string $tempDir Path to the temporary directory
     */
    protected function cleanupTempDirectory(string $tempDir): void
    {
        exec("rm -rf " . escapeshellarg($tempDir));
    }
}
