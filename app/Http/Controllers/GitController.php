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
     * Upload a dist folder to a repository.
     * This method extracts the uploaded archive and copies its contents to the dist folder
     * in the repository path. No git operations are performed.
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
                'branch' => 'nullable|string|max:100', // Branch is optional now
            ]);

            // Additional validation for the uploaded file
            $uploadedFile = $request->file('dist_folder');
            if (!$uploadedFile) {
                return $this->errorResponse('No file was uploaded or the upload failed', 400);
            }

            if (!$uploadedFile->isValid()) {
                $errorMessage = $uploadedFile->getError()
                    ? 'Upload error: ' . $this->getUploadErrorMessage($uploadedFile->getError())
                    : 'The uploaded file is not valid';

                Log::error('Invalid upload file', [
                    'error_code' => $uploadedFile->getError(),
                    'error_message' => $errorMessage
                ]);

                return $this->errorResponse($errorMessage, 400);
            }

            // Create temporary directory
            $tempDir = $this->createTempDirectory();

            try {
                // Extract the uploaded file
                $extractPath = $this->extractUploadedFile(
                    $request->file('dist_folder'),
                    $tempDir
                );
            } catch (Exception $extractException) {
                // Clean up temporary files if they exist
                if ($tempDir && file_exists($tempDir)) {
                    $this->cleanupTempDirectory($tempDir);
                }

                // Log the extraction error
                $uploadedFile = $request->file('dist_folder');
                $logData = ['error' => $extractException->getMessage()];

                // Only try to access file properties if the file object exists and is valid
                if ($uploadedFile && $uploadedFile->isValid()) {
                    try {
                        $logData['file_type'] = $uploadedFile->getClientOriginalExtension();
                        $logData['file_name'] = $uploadedFile->getClientOriginalName();
                        $logData['file_size'] = $uploadedFile->getSize();
                    } catch (Exception $fileException) {
                        // If we can't access file properties, log that separately
                        Log::warning("Could not access uploaded file properties: {$fileException->getMessage()}");
                    }
                } else {
                    $logData['file_status'] = 'File is not valid or no longer accessible';
                }

                Log::error("Dist folder extraction failed", $logData);

                return $this->errorResponse("Failed to extract dist folder archive: {$extractException->getMessage()}", 500, $extractException);
            }

            // Log the activity
            RepoActivityLog::makeRepoLogs(
                Auth::id(),
                $repoId,
                'upload-dist',
                $validated['commit_message']
            );

            // Run commands to copy the dist folder
            $commands = $this->buildDistFolderCommands(
                $validated['branch'] ?? 'main', // Default to 'main' if branch is not provided
                $extractPath,
                $validated['commit_message']
            );

            $output = $this->gitService->runMultipleCommands($commands);

            // Clean up temporary files
            $this->cleanupTempDirectory($tempDir);

            return $this->successResponse(
                ['output' => $output],
                'Dist folder uploaded successfully'
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
        // Validate the uploaded file
        if (!$file || !$file->isValid()) {
            throw new Exception("Invalid or corrupted upload file. Please try uploading again.");
        }

        // Get file details before moving it
        try {
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileExtension = $file->getClientOriginalExtension();

            // Log file details for debugging
            Log::info("Processing uploaded file", [
                'name' => $fileName,
                'size' => $fileSize,
                'extension' => $fileExtension
            ]);

            // Check if file size is zero
            if ($fileSize <= 0) {
                throw new Exception("Uploaded file is empty (0 bytes). Please check the file and try again.");
            }
        } catch (Exception $e) {
            throw new Exception("Failed to read upload file details: " . $e->getMessage());
        }

        // Move the file to the temporary directory
        try {
            $file->move($tempDir, $fileName);
        } catch (Exception $e) {
            throw new Exception("Failed to move uploaded file to temporary directory: " . $e->getMessage());
        }

        // Create extraction directory
        $extractPath = $tempDir . '/extracted';
        if (!mkdir($extractPath, 0777, true)) {
            throw new Exception("Failed to create extraction directory: {$extractPath}");
        }

        // Verify the file was moved successfully and exists
        $filePath = $tempDir . '/' . $fileName;
        if (!file_exists($filePath)) {
            throw new Exception("File not found after moving to temporary directory: {$filePath}");
        }

        // Get file size after moving to verify it's not corrupted
        if (filesize($filePath) <= 0) {
            throw new Exception("File is empty after moving to temporary directory. The upload may have been corrupted.");
        }

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
        $extension = strtolower($extension);

        // Check if RAR and if unrar is available
        if ($extension === 'rar') {
            // Check if unrar command is available (works on both Linux and Windows)
            exec('unrar 2>&1', $unrarOutput, $unrarReturnCode);

            if ($unrarReturnCode === 127) { // Command not found
                // Log the error for server administrators
                Log::error("The unrar command is not available on this server. RAR extraction will fail.");

                // TODO: Consider adding a PHP library for RAR extraction as a fallback
                // For example: nelexa/zip (https://github.com/Ne-Lexa/php-zip)
                // This would require adding the library to composer.json and implementing the extraction here

                throw new Exception('The unrar command is not available on this server. Please install it using: sudo apt-get install unrar (Linux) or download from rarlab.com (Windows)');
            }
        }

        $command = match ($extension) {
            'tar' => "tar -xf " . escapeshellarg($filePath) . " -C " . escapeshellarg($extractPath),
            'gz', 'tgz' => "tar -xzf " . escapeshellarg($filePath) . " -C " . escapeshellarg($extractPath),
            'rar' => "unrar x " . escapeshellarg($filePath) . " " . escapeshellarg($extractPath),
            default => throw new Exception('Unsupported archive format: ' . $extension),
        };

        // Log the command being executed for debugging purposes
        Log::info("Executing archive extraction command: " . preg_replace('/\s+/', ' ', $command));

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            Log::error("Archive extraction failed: " . $errorMessage);

            if ($extension === 'rar') {
                // Provide more specific error message for RAR files
                if (empty($errorMessage)) {
                    $errorMessage = "Unknown error occurred during RAR extraction. Please check if the RAR file is valid and not corrupted.";
                } elseif (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'No such file') !== false) {
                    $errorMessage = "The RAR file could not be found or accessed.";
                } elseif (strpos($errorMessage, 'permission') !== false) {
                    $errorMessage = "Permission denied when trying to extract the RAR file. Please check file permissions.";
                } elseif (strpos($errorMessage, 'corrupt') !== false || strpos($errorMessage, 'CRC failed') !== false) {
                    $errorMessage = "The RAR file appears to be corrupted or incomplete.";
                }
            }

            throw new Exception('Failed to extract archive: ' . $errorMessage);
        }
    }

    /**
     * Build commands to update the dist folder.
     * This method creates commands to remove the existing dist folder,
     * create a new one, and copy the extracted files to it.
     * No git operations are performed.
     *
     * @param string $branch Branch name (not used, kept for backward compatibility)
     * @param string $extractPath Path to extracted files
     * @param string $commitMessage Commit message (not used, kept for backward compatibility)
     * @return array Array of commands
     */
    protected function buildDistFolderCommands(string $branch, string $extractPath, string $commitMessage): array
    {
        return [
            "sudo rm -rf dist",  // Remove existing dist folder
            "sudo mkdir -p dist", // Create new dist folder
            "sudo cp -r " . escapeshellarg($extractPath) . "/* dist/" // Copy extracted files to dist folder
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

    /**
     * Get a human-readable error message for PHP file upload error codes.
     *
     * @param int $errorCode PHP file upload error code
     * @return string Human-readable error message
     */
    protected function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error',
        };
    }
}
